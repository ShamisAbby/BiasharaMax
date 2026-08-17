<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Http\Requests\ProductStoreRequest;
use App\Domain\Inventory\Http\Requests\ProductUpdateRequest;
use App\Domain\Inventory\Http\Resources\BrandResource;
use App\Domain\Inventory\Http\Resources\CategoryResource;
use App\Domain\Inventory\Http\Resources\ProductResource;
use App\Domain\Inventory\Http\Resources\UnitResource;
use App\Domain\Inventory\Models\Brand;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\Unit;
use App\Domain\Inventory\Services\ProductService;
use App\Domain\Purchasing\Http\Resources\SupplierResource;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Subscription\Exceptions\PlanLimitExceededException;
use App\Domain\Subscription\Services\SubscriptionLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly SubscriptionLimitService $limits,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $businessId = $request->user()->business_id;

        $products = Product::query()
            ->where('business_id', $businessId)
            ->with(['category', 'brand', 'unit', 'inventories'])
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->string('category_id')))
            ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->string('brand_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('product_type'), fn ($query) => $query->where('product_type', $request->string('product_type')))
            ->orderBy($request->string('sort_by', 'name')->value(), $request->string('sort_direction', 'asc')->value())
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/Products/Index', [
            'products' => ProductResource::collection($products),
            'filters' => $request->only(['search', 'category_id', 'brand_id', 'status', 'product_type', 'sort_by', 'sort_direction']),
            'categories' => CategoryResource::collection(Category::query()->where('business_id', $businessId)->orderBy('name')->get()),
            'brands' => BrandResource::collection(Brand::query()->where('business_id', $businessId)->orderBy('name')->get()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Inventory/Products/Form', $this->formOptions($request));
    }

    public function store(ProductStoreRequest $request): RedirectResponse
    {
        try {
            $this->limits->ensureCanAdd($request->user()->business, 'products');
        } catch (PlanLimitExceededException $e) {
            return back()->withErrors(['product' => $e->getMessage()]);
        }

        $product = $this->productService->create($request->user()->business_id, $request->validated());

        return redirect()->route('inventory.products.show', $product)->with('status', 'product-created');
    }

    public function show(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);

        $product->load([
            'category', 'brand', 'unit', 'defaultSupplier',
            'variants', 'tags', 'collections', 'suppliers',
            'inventories.warehouse', 'batches', 'serials',
        ]);

        return Inertia::render('Inventory/Products/Show', [
            'product' => new ProductResource($product),
        ]);
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        $product->load(['variants', 'tags', 'collections', 'suppliers']);

        return Inertia::render('Inventory/Products/Form', [
            ...$this->formOptions($request),
            'product' => new ProductResource($product),
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        $this->productService->update($product, $request->validated());

        return redirect()->route('inventory.products.show', $product)->with('status', 'product-updated');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('inventory.products.index')->with('status', 'product-deleted');
    }

    public function duplicate(Product $product): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $this->authorize('view', $product);

        $copy = $this->productService->duplicate($product);

        return redirect()->route('inventory.products.edit', $copy)->with('status', 'product-duplicated');
    }

    public function archive(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->productService->archive($product);

        return back()->with('status', 'product-archived');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $businessId = $request->user()->business_id;

        return [
            'categories' => CategoryResource::collection(Category::query()->where('business_id', $businessId)->orderBy('name')->get()),
            'brands' => BrandResource::collection(Brand::query()->where('business_id', $businessId)->orderBy('name')->get()),
            'units' => UnitResource::collection(Unit::query()->where('business_id', $businessId)->orderBy('name')->get()),
            'suppliers' => SupplierResource::collection(Supplier::query()->where('business_id', $businessId)->orderBy('name')->get()),
        ];
    }
}
