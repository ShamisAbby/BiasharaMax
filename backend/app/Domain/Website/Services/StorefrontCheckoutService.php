<?php

namespace App\Domain\Website\Services;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Services\SaleService;
use App\Domain\Website\Exceptions\CheckoutException;
use Illuminate\Support\Facades\DB;

/**
 * Wraps the real SaleService — an online order is a real Sale with
 * source=online, not a parallel order model. Inventory deduction, CRM
 * loyalty-tier recalculation and accounting all happen automatically via
 * the same SaleCompleted event POS sales already use.
 */
class StorefrontCheckoutService
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly StorefrontCartService $cartService,
    ) {}

    /**
     * @param  array{name: string, phone: string, email?: ?string, delivery_address?: ?string, payment_method: string, payment_reference?: ?string, notes?: ?string}  $buyer
     */
    public function checkout(string $businessId, array $buyer): Sale
    {
        $cart = $this->cartService->summary($businessId);

        if (empty($cart['lines'])) {
            throw CheckoutException::emptyCart();
        }

        [$branch, $warehouse] = $this->resolveFulfilmentLocation($businessId);

        return DB::transaction(function () use ($businessId, $buyer, $cart, $branch, $warehouse) {
            $customer = $this->resolveCustomer($businessId, $buyer, $cart['subtotal']);

            $items = array_map(fn (array $line) => [
                'product_id' => $line['product']->id,
                'quantity' => $line['quantity'],
            ], $cart['lines']);

            $payments = [];
            if ($buyer['payment_method'] !== 'pay_on_delivery') {
                $payments[] = [
                    'amount' => $cart['subtotal'],
                    'payment_method' => $buyer['payment_method'],
                    'reference_number' => $buyer['payment_reference'] ?? null,
                ];
            }

            $sale = $this->saleService->create([
                'business_id' => $businessId,
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'customer_id' => $customer->id,
                'items' => $items,
                'payments' => $payments,
                'source' => Sale::SOURCE_ONLINE,
                'delivery_address' => $buyer['delivery_address'] ?? null,
                'notes' => $buyer['notes'] ?? null,
            ]);

            $this->cartService->clear($businessId);

            return $sale;
        });
    }

    /**
     * @return array{0: Branch, 1: Warehouse}
     */
    private function resolveFulfilmentLocation(string $businessId): array
    {
        $branch = Branch::query()->where('business_id', $businessId)->where('is_main', true)->first()
            ?? Branch::query()->where('business_id', $businessId)->orderBy('created_at')->first();

        if (! $branch) {
            throw CheckoutException::noFulfilmentLocation();
        }

        $warehouse = Warehouse::query()->where('branch_id', $branch->id)->where('is_default', true)->first()
            ?? Warehouse::query()->where('branch_id', $branch->id)->orderBy('created_at')->first();

        if (! $warehouse) {
            throw CheckoutException::noFulfilmentLocation();
        }

        return [$branch, $warehouse];
    }

    /**
     * @param  array{name: string, phone: string, email?: ?string, delivery_address?: ?string, payment_method: string}  $buyer
     */
    private function resolveCustomer(string $businessId, array $buyer, string $orderTotal): Customer
    {
        $customer = Customer::query()->firstOrCreate(
            ['business_id' => $businessId, 'phone' => $buyer['phone']],
            ['name' => $buyer['name']],
        );

        $customer->name = $buyer['name'];
        $customer->email = $buyer['email'] ?? $customer->email;
        $customer->address = $buyer['delivery_address'] ?? $customer->address;

        // "Pay on delivery" reuses the real credit-sale ledger (rather
        // than a parallel "pending payment" concept) — just enough
        // headroom is granted to cover this order on top of whatever the
        // customer already owes.
        if ($buyer['payment_method'] === 'pay_on_delivery') {
            $customer->customer_type = Customer::TYPE_CREDIT;
            $neededLimit = bcadd((string) $customer->current_balance, $orderTotal, 2);

            if (bccomp((string) $customer->credit_limit, $neededLimit, 2) < 0) {
                $customer->credit_limit = $neededLimit;
            }
        }

        $customer->save();

        return $customer;
    }
}
