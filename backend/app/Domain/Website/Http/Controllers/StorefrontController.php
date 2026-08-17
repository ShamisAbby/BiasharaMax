<?php

namespace App\Domain\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Business;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Product;
use App\Domain\Sales\Models\Sale;
use App\Domain\Website\Exceptions\CheckoutException;
use App\Domain\Website\Http\Requests\StorefrontCartAddRequest;
use App\Domain\Website\Http\Requests\StorefrontCartUpdateRequest;
use App\Domain\Website\Http\Requests\StorefrontCheckoutRequest;
use App\Domain\Website\Http\Requests\StorefrontEnquiryRequest;
use App\Domain\Website\Http\Resources\StorefrontProductResource;
use App\Domain\Website\Models\ProductEnquiry;
use App\Domain\Website\Services\ProductEnquiryService;
use App\Domain\Website\Services\StorefrontCartService;
use App\Domain\Website\Services\StorefrontCatalogService;
use App\Domain\Website\Services\StorefrontCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly StorefrontCatalogService $catalogService,
        private readonly StorefrontCartService $cartService,
        private readonly StorefrontCheckoutService $checkoutService,
        private readonly ProductEnquiryService $enquiryService,
    ) {}

    public function products(Request $request, Business $business): Response
    {
        $products = $this->catalogService->paginate(
            $business->id,
            $request->string('search')->trim()->value() ?: null,
            $request->string('category')->trim()->value() ?: null,
        );

        return Inertia::render('Storefront/Products', [
            'business' => $this->businessInfo($business),
            'products' => StorefrontProductResource::collection($products),
            'categories' => Category::query()->where('business_id', $business->id)->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function productShow(Business $business, string $slug): Response
    {
        $product = $this->catalogService->find($business->id, $slug);

        abort_unless($product, 404);

        return Inertia::render('Storefront/ProductShow', [
            'business' => $this->businessInfo($business),
            'product' => new StorefrontProductResource($product),
        ]);
    }

    public function storeEnquiry(StorefrontEnquiryRequest $request, Business $business, string $slug): RedirectResponse
    {
        $product = $this->catalogService->find($business->id, $slug);
        abort_unless($product, 404);

        $this->enquiryService->create([
            ...$request->validated(),
            'business_id' => $business->id,
            'product_id' => $product->id,
        ]);

        return back()->with('status', 'enquiry-sent');
    }

    public function cart(Business $business): Response
    {
        $cart = $this->cartService->summary($business->id);

        return Inertia::render('Storefront/Cart', [
            'business' => $this->businessInfo($business),
            'cart' => $this->serializeCart($cart),
        ]);
    }

    public function addToCart(StorefrontCartAddRequest $request, Business $business): RedirectResponse
    {
        $product = Product::query()->where('business_id', $business->id)->findOrFail($request->validated('product_id'));

        $this->cartService->add($business->id, $product->id, $request->validated('quantity'));

        return back()->with('status', 'added-to-cart');
    }

    public function updateCartItem(StorefrontCartUpdateRequest $request, Business $business, string $product): RedirectResponse
    {
        $this->cartService->update($business->id, $product, $request->validated('quantity'));

        return back()->with('status', 'cart-updated');
    }

    public function removeCartItem(Business $business, string $product): RedirectResponse
    {
        $this->cartService->remove($business->id, $product);

        return back()->with('status', 'cart-updated');
    }

    public function checkout(Business $business): Response
    {
        $cart = $this->cartService->summary($business->id);

        return Inertia::render('Storefront/Checkout', [
            'business' => $this->businessInfo($business),
            'cart' => $this->serializeCart($cart),
        ]);
    }

    public function placeOrder(StorefrontCheckoutRequest $request, Business $business): RedirectResponse
    {
        try {
            $sale = $this->checkoutService->checkout($business->id, $request->validated());
        } catch (CheckoutException $e) {
            throw ValidationException::withMessages(['checkout' => $e->getMessage()]);
        } catch (InsufficientStockException $e) {
            throw ValidationException::withMessages(['checkout' => 'One or more items in your cart are no longer in stock. Please review your cart.']);
        }

        return redirect()->route('public.website.orders.show', [$business->slug, $sale->sale_number])
            ->with('status', 'order-placed');
    }

    public function orderShow(Business $business, string $saleNumber): Response
    {
        $sale = Sale::query()
            ->where('business_id', $business->id)
            ->where('sale_number', $saleNumber)
            ->where('source', Sale::SOURCE_ONLINE)
            ->with('items.product')
            ->firstOrFail();

        return Inertia::render('Storefront/OrderConfirmation', [
            'business' => $this->businessInfo($business),
            'order' => [
                'sale_number' => $sale->sale_number,
                'total_amount' => $sale->total_amount,
                'payment_status' => $sale->payment_status,
                'delivery_address' => $sale->delivery_address,
                'items' => $sale->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function businessInfo(Business $business): array
    {
        $business->loadMissing('businessType.websiteTemplate');
        $template = $business->businessType?->websiteTemplate;

        return [
            'name' => $business->name,
            'slug' => $business->slug,
            'logo_path' => $business->logo_path,
            'email' => $business->email,
            'phone' => $business->phone,
            'theme_colors' => $template?->theme_colors,
            'typography' => $template?->typography,
        ];
    }

    /**
     * @param  array{lines: array<int, array{product: Product, quantity: int, line_total: string}>, subtotal: string}  $cart
     * @return array<string, mixed>
     */
    private function serializeCart(array $cart): array
    {
        return [
            'lines' => array_map(fn (array $line) => [
                'product_id' => $line['product']->id,
                'name' => $line['product']->name,
                'slug' => $line['product']->slug,
                'selling_price' => $line['product']->selling_price,
                'quantity' => $line['quantity'],
                'line_total' => $line['line_total'],
                'image' => $line['product']->images->first()?->path,
            ], $cart['lines']),
            'subtotal' => $cart['subtotal'],
        ];
    }
}
