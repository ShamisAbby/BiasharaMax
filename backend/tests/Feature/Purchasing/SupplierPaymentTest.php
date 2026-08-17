<?php

namespace Tests\Feature\Purchasing;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Models\SupplierDebtTransaction;
use App\Domain\Purchasing\Models\SupplierPayment;
use App\Domain\RBAC\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SupplierPaymentTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * @return array{0: PurchaseOrder, 1: Supplier}
     */
    private function makeOrderWithBalance(string $businessId, string $totalAmount): array
    {
        $branch = Branch::query()->where('business_id', $businessId)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $businessId)->firstOrFail();
        $supplier = Supplier::create(['business_id' => $businessId, 'name' => 'Acme Supplies', 'status' => Supplier::STATUS_ACTIVE]);

        $po = PurchaseOrder::create([
            'business_id' => $businessId,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-PAY-'.uniqid(),
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_SENT,
            'total_amount' => $totalAmount,
            'balance_due' => $totalAmount,
        ]);

        return [$po, $supplier];
    }

    public function test_recording_a_payment_reduces_balance_and_updates_supplier(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$po, $supplier] = $this->makeOrderWithBalance($business->id, '5000.00');
        $supplier->update(['current_balance' => '5000.00']);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/payments", [
            'amount' => 2000,
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertSame('2000.00', $po->paid_amount);
        $this->assertSame('3000.00', $po->balance_due);
        $this->assertSame(PurchaseOrder::PAYMENT_STATUS_PARTIAL, $po->payment_status);

        $supplier->refresh();
        $this->assertSame('3000.00', $supplier->current_balance);

        $payment = SupplierPayment::query()->where('purchase_order_id', $po->id)->firstOrFail();
        $this->assertSame('2000.00', $payment->amount);

        $debt = SupplierDebtTransaction::query()->where('supplier_payment_id', $payment->id)->firstOrFail();
        $this->assertSame(SupplierDebtTransaction::TYPE_PAYMENT, $debt->type);
        $this->assertSame('5000.00', $debt->balance_before);
        $this->assertSame('3000.00', $debt->balance_after);
    }

    public function test_paying_off_the_full_balance_marks_the_order_paid(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$po, $supplier] = $this->makeOrderWithBalance($business->id, '1500.00');
        $supplier->update(['current_balance' => '1500.00']);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/payments", [
            'amount' => 1500,
            'payment_method' => 'bank_transfer',
        ])->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertSame('0.00', $po->balance_due);
        $this->assertSame(PurchaseOrder::PAYMENT_STATUS_PAID, $po->payment_status);
    }

    public function test_overpaying_a_purchase_order_balance_fails_validation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$po] = $this->makeOrderWithBalance($business->id, '1000.00');

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/payments", [
            'amount' => 999999,
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('amount');

        $this->assertSame('0.00', $po->refresh()->paid_amount);
    }

    public function test_cancelled_purchase_order_cannot_receive_a_payment(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$po] = $this->makeOrderWithBalance($business->id, '1000.00');
        $po->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/payments", [
            'amount' => 100,
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('amount');
    }

    public function test_employee_without_supplier_payments_permission_cannot_record_a_payment(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [$po] = $this->makeOrderWithBalance($business->id, '1000.00');

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create(['business_id' => $business->id, 'role_id' => $plainEmployeeRole->id]);

        $this->actingAs($employee)->post("/purchasing/orders/{$po->id}/payments", [
            'amount' => 100,
            'payment_method' => 'cash',
        ])->assertForbidden();

        $this->assertSame('0.00', $po->refresh()->paid_amount);
    }
}
