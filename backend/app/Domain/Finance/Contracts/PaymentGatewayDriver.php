<?php

namespace App\Domain\Finance\Contracts;

use App\Domain\Finance\Models\PaymentTransaction;

/**
 * Every gateway driver wraps one provider's real API contract. A driver
 * that refuses to act because its gateway has no credentials configured
 * is correct behavior, not a bug — see PaymentGateway::isConfigured().
 */
interface PaymentGatewayDriver
{
    /**
     * Initiate a charge for the given transaction.
     *
     * @return array{successful: bool, external_id: ?string, raw: array<string, mixed>}
     */
    public function charge(PaymentTransaction $transaction): array;

    /**
     * Verify the current state of a previously initiated charge.
     *
     * @return array{successful: bool, status: string, raw: array<string, mixed>}
     */
    public function verify(string $externalTransactionId): array;

    /**
     * Refund all or part of a successful transaction.
     *
     * @return array{successful: bool, raw: array<string, mixed>}
     */
    public function refund(PaymentTransaction $transaction, string $amount): array;
}
