<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Events\SalePaymentRecorded;
use App\Domain\Sales\Exceptions\CreditSaleException;
use App\Domain\Sales\Models\CustomerDebtTransaction;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SalePayment;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Records a payment against an existing sale's outstanding balance (e.g. a
 * credit customer settling a debt later) — distinct from the up-front
 * payments captured at the moment a sale is created in SaleService::create().
 */
class SalePaymentService
{
    /**
     * @param  array{amount: float|string, payment_method?: string, reference_number?: ?string, notes?: ?string, received_by?: ?string}  $data
     *
     * @throws CreditSaleException
     */
    public function record(Sale $sale, array $data): SalePayment
    {
        return DB::transaction(function () use ($sale, $data) {
            if ($sale->isVoid()) {
                throw CreditSaleException::alreadyVoided();
            }

            $amount = (string) $data['amount'];

            if (bccomp($amount, (string) $sale->balance_due, 2) > 0) {
                throw CreditSaleException::paymentExceedsBalance((string) $sale->balance_due);
            }

            $payment = $sale->payments()->create([
                'business_id' => $sale->business_id,
                'customer_id' => $sale->customer_id,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
                'received_by' => $data['received_by'] ?? null,
            ]);

            $newPaidAmount = bcadd((string) $sale->paid_amount, $amount, 2);
            $newBalanceDue = bcsub((string) $sale->total_amount, $newPaidAmount, 2);

            $sale->update([
                'paid_amount' => $newPaidAmount,
                'balance_due' => $newBalanceDue,
                'payment_status' => bccomp($newBalanceDue, '0', 2) <= 0
                    ? Sale::PAYMENT_STATUS_PAID
                    : Sale::PAYMENT_STATUS_PARTIAL,
            ]);

            if ($sale->customer_id) {
                $customer = $sale->customer;
                $currency = $customer->business?->currency ?? 'TZS';
                $amountMoney = Money::fromDecimal($amount, $currency);
                $balanceBefore = $customer->currentBalanceMoney($currency);
                $balanceAfter = $balanceBefore->subtract($amountMoney);

                CustomerDebtTransaction::create([
                    'business_id' => $sale->business_id,
                    'customer_id' => $customer->id,
                    'sale_id' => $sale->id,
                    'sale_payment_id' => $payment->id,
                    'type' => CustomerDebtTransaction::TYPE_PAYMENT,
                    'amount' => $amountMoney->toDecimalString(),
                    'amount_minor' => $amountMoney->minorUnits(),
                    'balance_before' => $balanceBefore->toDecimalString(),
                    'balance_before_minor' => $balanceBefore->minorUnits(),
                    'balance_after' => $balanceAfter->toDecimalString(),
                    'balance_after_minor' => $balanceAfter->minorUnits(),
                    'notes' => "Payment against sale {$sale->sale_number}",
                    'created_by' => $data['received_by'] ?? null,
                ]);

                $customer->update([
                    'current_balance' => $balanceAfter->toDecimalString(),
                    'current_balance_minor' => $balanceAfter->minorUnits(),
                ]);
            }

            SalePaymentRecorded::dispatch($payment);

            return $payment;
        });
    }
}
