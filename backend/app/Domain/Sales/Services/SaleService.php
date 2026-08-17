<?php

namespace App\Domain\Sales\Services;

use App\Domain\Inventory\Models\Product;
use App\Domain\Sales\Events\SaleCompleted;
use App\Domain\Sales\Events\SaleVoided;
use App\Domain\Sales\Exceptions\CreditSaleException;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\CustomerDebtTransaction;
use App\Domain\Sales\Models\Sale;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single entry point for creating and voiding sales. A sale is always
 * created as a complete, atomic unit — items, payments, the customer debt
 * ledger entry (if sold on credit), and the resulting inventory deduction
 * (via the SaleCompleted event) all succeed or all roll back together.
 */
class SaleService
{
    /**
     * @param  array{
     *     business_id: string,
     *     branch_id: string,
     *     warehouse_id: string,
     *     customer_id?: ?string,
     *     items: array<int, array{product_id: string, product_variant_id?: ?string, product_batch_id?: ?string, quantity: float|string, unit_price?: float|string|null, discount_amount?: float|string|null}>,
     *     discount_amount?: float|string|null,
     *     payments?: array<int, array{amount: float|string, payment_method?: string, reference_number?: ?string}>,
     *     notes?: ?string,
     *     sold_by?: ?string,
     *     source?: string,
     *     delivery_address?: ?string,
     *     idempotency_key?: ?string,
     * }  $data
     *
     * @throws CreditSaleException
     */
    public function create(array $data): Sale
    {
        // Offline clients (Flutter Desktop) generate this the moment the
        // cashier completes the sale, before there's any network path to
        // the server. A retried sync push after a dropped connection must
        // not double-create the sale — return the original instead.
        if (! empty($data['idempotency_key'])) {
            $existing = Sale::query()
                ->where('business_id', $data['business_id'])
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing !== null) {
                return $existing->load(['items', 'payments', 'customer']);
            }
        }

        return DB::transaction(function () use ($data) {
            $customer = ! empty($data['customer_id'])
                ? Customer::query()->findOrFail($data['customer_id'])
                : null;

            [$lineItems, $subtotal, $itemDiscount, $taxAmount] = $this->buildLineItems($data['items']);

            $discountAmount = (string) ($data['discount_amount'] ?? 0);
            $totalDiscount = bcadd($itemDiscount, $discountAmount, 2);
            $totalAmount = bcsub(bcadd($subtotal, $taxAmount, 2), $discountAmount, 2);

            $paidAmount = '0.00';
            foreach ($data['payments'] ?? [] as $payment) {
                $paidAmount = bcadd($paidAmount, (string) $payment['amount'], 2);
            }

            $balanceDue = bcsub($totalAmount, $paidAmount, 2);

            if (bccomp($balanceDue, '0', 2) > 0) {
                $this->assertCreditSaleAllowed($customer, $balanceDue);
            }

            $sale = Sale::create([
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'warehouse_id' => $data['warehouse_id'],
                'customer_id' => $customer?->id,
                'sale_number' => $this->generateSaleNumber($data['business_id']),
                'status' => Sale::STATUS_COMPLETED,
                'payment_status' => $this->paymentStatus($paidAmount, $balanceDue),
                'source' => $data['source'] ?? Sale::SOURCE_POS,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'notes' => $data['notes'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'sold_by' => $data['sold_by'] ?? null,
            ]);

            foreach ($lineItems as $lineItem) {
                $sale->items()->create($lineItem);
            }

            foreach ($data['payments'] ?? [] as $payment) {
                $sale->payments()->create([
                    'business_id' => $sale->business_id,
                    'customer_id' => $sale->customer_id,
                    'amount' => $payment['amount'],
                    'payment_method' => $payment['payment_method'] ?? 'cash',
                    'reference_number' => $payment['reference_number'] ?? null,
                    'paid_at' => now(),
                    'received_by' => $data['sold_by'] ?? null,
                ]);
            }

            if ($customer && bccomp($balanceDue, '0', 2) > 0) {
                $this->chargeCustomer($customer, $sale, $balanceDue);
            }

            $sale->load('items');
            SaleCompleted::dispatch($sale);

            return $sale->load(['items', 'payments', 'customer']);
        });
    }

    public function void(Sale $sale, string $reason, ?string $voidedBy = null): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $voidedBy) {
            if ($sale->isVoid()) {
                throw CreditSaleException::alreadyVoided();
            }

            $sale->update([
                'status' => Sale::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $voidedBy,
                'void_reason' => $reason,
            ]);

            if ($sale->customer_id) {
                $this->reverseCustomerDebtForSale($sale);
            }

            $sale->load('items');
            SaleVoided::dispatch($sale);

            return $sale->refresh();
        });
    }

    /**
     * @param  array<int, array{product_id: string, product_variant_id?: ?string, product_batch_id?: ?string, quantity: float|string, unit_price?: float|string|null, discount_amount?: float|string|null}>  $items
     * @return array{0: array<int, array<string, mixed>>, 1: string, 2: string, 3: string}
     */
    private function buildLineItems(array $items): array
    {
        $lineItems = [];
        $subtotal = '0.00';
        $totalDiscount = '0.00';
        $totalTax = '0.00';

        foreach ($items as $item) {
            $product = Product::query()->findOrFail($item['product_id']);

            $quantity = (string) $item['quantity'];
            $unitPrice = (string) ($item['unit_price'] ?? $product->selling_price);
            $discount = (string) ($item['discount_amount'] ?? 0);
            $lineSubtotal = bcmul($quantity, $unitPrice, 2);
            $taxableAmount = bcsub($lineSubtotal, $discount, 2);
            $lineTax = bcmul($taxableAmount, bcdiv((string) $product->tax_rate, '100', 4), 2);
            $lineTotal = bcadd($taxableAmount, $lineTax, 2);

            $lineItems[] = [
                'product_id' => $product->id,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'product_batch_id' => $item['product_batch_id'] ?? null,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_cost' => $product->cost_price,
                'discount_amount' => $discount,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
            ];

            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
            $totalDiscount = bcadd($totalDiscount, $discount, 2);
            $totalTax = bcadd($totalTax, $lineTax, 2);
        }

        return [$lineItems, $subtotal, $totalDiscount, $totalTax];
    }

    /**
     * $balanceDue is still a decimal string here — Sale's own money columns
     * are cut over to Money in the Sales context (later in the rollout
     * order, docs/ADR/0002-money-format-migration.md), not this one. Bridge
     * via Money::fromDecimal() rather than touching Sale's fields early.
     */
    private function assertCreditSaleAllowed(?Customer $customer, string $balanceDue): void
    {
        if (! $customer) {
            throw CreditSaleException::customerRequired();
        }

        if ($customer->customer_type !== Customer::TYPE_CREDIT) {
            throw CreditSaleException::customerNotOnCreditTerms($customer->name);
        }

        $currency = $customer->business?->currency ?? 'TZS';
        $wouldBeBalance = $customer->currentBalanceMoney($currency)->add(Money::fromDecimal($balanceDue, $currency));

        if ($wouldBeBalance->minorUnits() > $customer->creditLimitMoney($currency)->minorUnits()) {
            throw CreditSaleException::creditLimitExceeded(
                $customer->name,
                $customer->creditLimitMoney($currency)->toDecimalString(),
                $wouldBeBalance->toDecimalString(),
            );
        }
    }

    private function chargeCustomer(Customer $customer, Sale $sale, string $amount): void
    {
        $currency = $customer->business?->currency ?? 'TZS';
        $amountMoney = Money::fromDecimal($amount, $currency);
        $balanceBefore = $customer->currentBalanceMoney($currency);
        $balanceAfter = $balanceBefore->add($amountMoney);

        CustomerDebtTransaction::create([
            'business_id' => $sale->business_id,
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'type' => CustomerDebtTransaction::TYPE_CHARGE,
            'amount' => $amountMoney->toDecimalString(),
            'amount_minor' => $amountMoney->minorUnits(),
            'balance_before' => $balanceBefore->toDecimalString(),
            'balance_before_minor' => $balanceBefore->minorUnits(),
            'balance_after' => $balanceAfter->toDecimalString(),
            'balance_after_minor' => $balanceAfter->minorUnits(),
            'notes' => "Credit sale {$sale->sale_number}",
            'created_by' => $sale->sold_by,
        ]);

        $customer->update([
            'current_balance' => $balanceAfter->toDecimalString(),
            'current_balance_minor' => $balanceAfter->minorUnits(),
        ]);
    }

    private function reverseCustomerDebtForSale(Sale $sale): void
    {
        $currency = $sale->customer?->business?->currency ?? 'TZS';

        $netChargeMinor = (int) CustomerDebtTransaction::query()
            ->where('sale_id', $sale->id)
            ->where('type', CustomerDebtTransaction::TYPE_CHARGE)
            ->sum('amount_minor');

        $netPaidMinor = (int) CustomerDebtTransaction::query()
            ->where('sale_id', $sale->id)
            ->where('type', CustomerDebtTransaction::TYPE_PAYMENT)
            ->sum('amount_minor');

        $netImpact = Money::fromMinorUnits($netChargeMinor - $netPaidMinor, $currency);

        if ($netImpact->isZero()) {
            return;
        }

        $customer = $sale->customer;
        $balanceBefore = $customer->currentBalanceMoney($currency);
        $balanceAfter = $balanceBefore->subtract($netImpact);
        $reversalAmount = Money::fromMinorUnits(-$netImpact->minorUnits(), $currency);

        CustomerDebtTransaction::create([
            'business_id' => $sale->business_id,
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'type' => CustomerDebtTransaction::TYPE_ADJUSTMENT,
            'amount' => $reversalAmount->toDecimalString(),
            'amount_minor' => $reversalAmount->minorUnits(),
            'balance_before' => $balanceBefore->toDecimalString(),
            'balance_before_minor' => $balanceBefore->minorUnits(),
            'balance_after' => $balanceAfter->toDecimalString(),
            'balance_after_minor' => $balanceAfter->minorUnits(),
            'notes' => "Void of sale {$sale->sale_number}",
            'created_by' => $sale->voided_by,
        ]);

        $customer->update([
            'current_balance' => $balanceAfter->toDecimalString(),
            'current_balance_minor' => $balanceAfter->minorUnits(),
        ]);
    }

    private function paymentStatus(string $paidAmount, string $balanceDue): string
    {
        if (bccomp($balanceDue, '0', 2) <= 0) {
            return Sale::PAYMENT_STATUS_PAID;
        }

        return bccomp($paidAmount, '0', 2) > 0
            ? Sale::PAYMENT_STATUS_PARTIAL
            : Sale::PAYMENT_STATUS_UNPAID;
    }

    private function generateSaleNumber(string $businessId): string
    {
        do {
            $candidate = 'SALE-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (Sale::query()->withTrashed()->where('business_id', $businessId)->where('sale_number', $candidate)->exists());

        return $candidate;
    }
}
