<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Exceptions\LoyaltyPointsException;
use App\Domain\CRM\Models\CustomerLoyaltyTransaction;
use App\Domain\Sales\Models\Customer;

/**
 * Points are awarded, redeemed, or corrected manually by staff — there is
 * no fabricated earn-rate formula (e.g. "1 point per $10 spent") tying
 * this to Sales, since no such program rule has been specified. Staff
 * record the real amount they're awarding/redeeming, exactly like an
 * Expense or Income entry.
 */
class CustomerLoyaltyService
{
    public function earn(Customer $customer, int $points, ?string $notes, ?string $createdBy): CustomerLoyaltyTransaction
    {
        return $this->record($customer, CustomerLoyaltyTransaction::TYPE_EARN, $points, $notes, $createdBy);
    }

    public function redeem(Customer $customer, int $points, ?string $notes, ?string $createdBy): CustomerLoyaltyTransaction
    {
        if ($points > $customer->loyalty_points) {
            throw LoyaltyPointsException::insufficientBalance($customer->name, $customer->loyalty_points, $points);
        }

        return $this->record($customer, CustomerLoyaltyTransaction::TYPE_REDEEM, -$points, $notes, $createdBy);
    }

    public function adjust(Customer $customer, int $delta, ?string $notes, ?string $createdBy): CustomerLoyaltyTransaction
    {
        return $this->record($customer, CustomerLoyaltyTransaction::TYPE_ADJUSTMENT, $delta, $notes, $createdBy);
    }

    private function record(Customer $customer, string $type, int $signedPoints, ?string $notes, ?string $createdBy): CustomerLoyaltyTransaction
    {
        $balanceBefore = $customer->loyalty_points;
        $balanceAfter = $balanceBefore + $signedPoints;

        $transaction = CustomerLoyaltyTransaction::create([
            'business_id' => $customer->business_id,
            'customer_id' => $customer->id,
            'type' => $type,
            'points' => $signedPoints,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        $customer->update(['loyalty_points' => $balanceAfter]);

        return $transaction;
    }
}
