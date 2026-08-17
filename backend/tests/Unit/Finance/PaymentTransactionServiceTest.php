<?php

namespace Tests\Unit\Finance;

use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Services\PaymentTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PaymentTransactionServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private PaymentTransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaymentTransactionService::class);
    }

    public function test_record_manual_creates_a_successful_transaction_with_a_timeline_entry(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $transaction = $this->service->recordManual([
            'business_id' => $business->id,
            'amount' => 50000,
            'currency' => 'TZS',
            'payment_method' => 'cash',
        ]);

        $this->assertSame(PaymentTransaction::STATUS_SUCCESSFUL, $transaction->status);
        $this->assertStringStartsWith('TXN-', $transaction->reference_number);
        $this->assertSame(1, $transaction->timeline()->count());
    }

    public function test_record_manual_respects_an_explicit_status(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $transaction = $this->service->recordManual([
            'business_id' => $business->id,
            'amount' => 50000,
            'currency' => 'TZS',
            'payment_method' => 'bank_transfer',
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        $this->assertSame(PaymentTransaction::STATUS_PENDING, $transaction->status);
    }

    public function test_retry_without_a_gateway_throws(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create(['business_id' => $business->id, 'status' => PaymentTransaction::STATUS_FAILED]);

        $this->expectException(\RuntimeException::class);

        $this->service->retry($transaction);
    }

    public function test_full_refund_marks_transaction_refunded_and_creates_a_refund_record(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create([
            'business_id' => $business->id,
            'amount' => 100000,
            'status' => PaymentTransaction::STATUS_SUCCESSFUL,
        ]);

        $refund = $this->service->refund($transaction, '100000', null, 'Customer cancelled');

        $this->assertSame(PaymentTransaction::TYPE_REFUND, $refund->type);
        $this->assertSame('100000.00', $refund->amount);
        $this->assertSame($transaction->id, $refund->parent_transaction_id);
        $this->assertSame(PaymentTransaction::STATUS_REFUNDED, $transaction->fresh()->status);
        $this->assertFalse($transaction->fresh()->isRefundable());
    }

    public function test_partial_refund_marks_transaction_partially_refunded(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create([
            'business_id' => $business->id,
            'amount' => 100000,
            'status' => PaymentTransaction::STATUS_SUCCESSFUL,
        ]);

        $refund = $this->service->refund($transaction, '40000');

        $this->assertSame(PaymentTransaction::TYPE_PARTIAL_REFUND, $refund->type);
        $this->assertSame(PaymentTransaction::STATUS_PARTIALLY_REFUNDED, $transaction->fresh()->status);
        $this->assertTrue($transaction->fresh()->isRefundable());
    }

    public function test_refund_amount_cannot_exceed_remaining_balance(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create([
            'business_id' => $business->id,
            'amount' => 100000,
            'status' => PaymentTransaction::STATUS_SUCCESSFUL,
        ]);

        $this->service->refund($transaction, '70000');

        $this->expectException(\RuntimeException::class);
        $this->service->refund($transaction->fresh(), '40000');
    }

    public function test_non_successful_transaction_cannot_be_refunded(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create([
            'business_id' => $business->id,
            'status' => PaymentTransaction::STATUS_FAILED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->refund($transaction, '10000');
    }

    public function test_manually_approve_marks_transaction_successful(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create([
            'business_id' => $business->id,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        $approved = $this->service->manuallyApprove($transaction);

        $this->assertSame(PaymentTransaction::STATUS_SUCCESSFUL, $approved->status);
        $this->assertNotNull($approved->paid_at);
        $this->assertSame(1, $transaction->timeline()->count());
    }
}
