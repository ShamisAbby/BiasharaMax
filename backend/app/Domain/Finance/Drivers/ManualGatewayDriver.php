<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;

/**
 * Cash and bank transfer have no API to call — money changes hands
 * outside the system and a SuperAdmin records it. "Charging" through
 * this driver just confirms the manual entry; there is nothing to
 * verify or refund automatically.
 */
class ManualGatewayDriver extends AbstractGatewayDriver
{
    public function charge(PaymentTransaction $transaction): array
    {
        return ['successful' => true, 'external_id' => null, 'raw' => ['manual' => true]];
    }

    public function verify(string $externalTransactionId): array
    {
        return ['successful' => true, 'status' => 'manual', 'raw' => ['manual' => true]];
    }

    public function refund(PaymentTransaction $transaction, string $amount): array
    {
        return ['successful' => true, 'raw' => ['manual' => true]];
    }
}
