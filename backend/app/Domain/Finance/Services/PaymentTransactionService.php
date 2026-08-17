<?php

namespace App\Domain\Finance\Services;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Models\PaymentTransactionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentTransactionService
{
    public function __construct(
        private readonly GatewayDriverResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordManual(array $data, ?PlatformUser $recordedBy = null): PaymentTransaction
    {
        return DB::transaction(function () use ($data, $recordedBy) {
            $transaction = PaymentTransaction::query()->create([
                ...$data,
                'reference_number' => $data['reference_number'] ?? $this->generateReferenceNumber(),
                'type' => $data['type'] ?? PaymentTransaction::TYPE_MANUAL,
                'status' => $data['status'] ?? PaymentTransaction::STATUS_SUCCESSFUL,
                'initiated_by' => $recordedBy?->id,
                'paid_at' => $data['paid_at'] ?? now(),
            ]);

            $this->writeTimelineEntry($transaction, PaymentTransactionLog::EVENT_CREATED, null, $transaction->status, 'Manually recorded by SuperAdmin.');

            return $transaction;
        });
    }

    public function retry(PaymentTransaction $transaction, ?PlatformUser $actor = null): PaymentTransaction
    {
        if (! $transaction->gateway) {
            throw new \RuntimeException('Cannot retry a transaction with no gateway.');
        }

        $previousStatus = $transaction->status;
        $transaction->update(['status' => PaymentTransaction::STATUS_PROCESSING]);

        $result = $this->resolver->resolve($transaction->gateway)->charge($transaction);

        $transaction->update([
            'status' => $result['successful'] ? PaymentTransaction::STATUS_SUCCESSFUL : PaymentTransaction::STATUS_FAILED,
            'external_transaction_id' => $result['external_id'] ?? $transaction->external_transaction_id,
            'metadata' => array_merge($transaction->metadata ?? [], ['last_retry' => $result['raw']]),
            'paid_at' => $result['successful'] ? now() : $transaction->paid_at,
            'failed_at' => $result['successful'] ? null : now(),
        ]);

        $this->writeTimelineEntry(
            $transaction->refresh(),
            PaymentTransactionLog::EVENT_RETRIED,
            $previousStatus,
            $transaction->status,
            $result['successful'] ? 'Retry succeeded.' : 'Retry failed.',
            $actor,
        );

        return $transaction;
    }

    public function refund(PaymentTransaction $transaction, string $amount, ?PlatformUser $actor = null, ?string $reason = null): PaymentTransaction
    {
        if (! $transaction->isRefundable()) {
            throw new \RuntimeException('This transaction cannot be refunded.');
        }

        $maxRefundable = bcsub($transaction->amount, $transaction->refunded_amount, 2);
        if (bccomp($amount, $maxRefundable, 2) > 0) {
            throw new \RuntimeException('Refund amount exceeds the remaining refundable balance.');
        }

        $result = ['successful' => true, 'raw' => ['manual' => true]];
        if ($transaction->gateway) {
            $result = $this->resolver->resolve($transaction->gateway)->refund($transaction, $amount);
        }

        if (! $result['successful']) {
            throw new \RuntimeException('The gateway rejected the refund request.');
        }

        return DB::transaction(function () use ($transaction, $amount, $actor, $reason) {
            $newRefundedTotal = bcadd($transaction->refunded_amount, $amount, 2);
            $isFullyRefunded = bccomp($newRefundedTotal, $transaction->amount, 2) >= 0;

            $transaction->update([
                'refunded_amount' => $newRefundedTotal,
                'status' => $isFullyRefunded ? PaymentTransaction::STATUS_REFUNDED : PaymentTransaction::STATUS_PARTIALLY_REFUNDED,
                'refunded_at' => now(),
            ]);

            $refundRecord = PaymentTransaction::query()->create([
                'business_id' => $transaction->business_id,
                'payment_gateway_id' => $transaction->payment_gateway_id,
                'payable_id' => $transaction->payable_id,
                'payable_type' => $transaction->payable_type,
                'parent_transaction_id' => $transaction->id,
                'type' => $isFullyRefunded ? PaymentTransaction::TYPE_REFUND : PaymentTransaction::TYPE_PARTIAL_REFUND,
                'reference_number' => $this->generateReferenceNumber('REF'),
                'amount' => $amount,
                'currency' => $transaction->currency,
                'status' => PaymentTransaction::STATUS_SUCCESSFUL,
                'payment_method' => $transaction->payment_method,
                'notes' => $reason,
                'initiated_by' => $actor?->id,
                'paid_at' => now(),
            ]);

            $this->writeTimelineEntry(
                $transaction->refresh(),
                PaymentTransactionLog::EVENT_REFUNDED,
                PaymentTransaction::STATUS_SUCCESSFUL,
                $transaction->status,
                "Refunded {$amount} {$transaction->currency}.".($reason ? " Reason: {$reason}" : ''),
                $actor,
            );

            return $refundRecord;
        });
    }

    public function manuallyApprove(PaymentTransaction $transaction, ?PlatformUser $actor = null): PaymentTransaction
    {
        $previousStatus = $transaction->status;

        $transaction->update(['status' => PaymentTransaction::STATUS_SUCCESSFUL, 'paid_at' => now()]);

        $this->writeTimelineEntry(
            $transaction->refresh(),
            PaymentTransactionLog::EVENT_MANUALLY_APPROVED,
            $previousStatus,
            $transaction->status,
            'Manually approved by SuperAdmin.',
            $actor,
        );

        return $transaction;
    }

    private function writeTimelineEntry(
        PaymentTransaction $transaction,
        string $event,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $message = null,
        ?PlatformUser $actor = null,
    ): void {
        PaymentTransactionLog::query()->create([
            'payment_transaction_id' => $transaction->id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'message' => $message,
            'actor_type' => $actor ? 'platform_user' : null,
            'actor_id' => $actor?->id,
        ]);
    }

    private function generateReferenceNumber(string $prefix = 'TXN'): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}
