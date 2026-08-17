<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PaymentTransactionManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_the_payments_index(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        PaymentTransaction::factory()->create(['business_id' => $business->id]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.payments.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Finance/Payments/Index')
                ->has('transactions.data', 1)
            );
    }

    public function test_platform_user_can_record_a_manual_payment(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')->post(route('platform.finance.payments.store'), [
            'business_id' => $business->id,
            'type' => 'manual',
            'amount' => 50000,
            'currency' => 'TZS',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payment_transactions', ['business_id' => $business->id, 'amount' => 50000]);
    }

    public function test_platform_user_can_view_a_transaction_with_its_timeline(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create(['business_id' => $business->id]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.payments.show', $transaction->id))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Finance/Payments/Show')
                ->where('transaction.id', $transaction->id)
            );
    }

    public function test_platform_user_can_approve_a_pending_payment(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create(['business_id' => $business->id, 'status' => PaymentTransaction::STATUS_PENDING]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.finance.payments.approve', $transaction->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(PaymentTransaction::STATUS_SUCCESSFUL, $transaction->fresh()->status);
    }

    public function test_platform_user_can_refund_a_successful_payment(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create([
            'business_id' => $business->id, 'amount' => 50000, 'status' => PaymentTransaction::STATUS_SUCCESSFUL,
        ]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.finance.payments.refund', $transaction->id), ['amount' => 50000])
            ->assertSessionHasNoErrors();

        $this->assertSame(PaymentTransaction::STATUS_REFUNDED, $transaction->fresh()->status);
    }

    public function test_retry_without_a_gateway_returns_a_friendly_error(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create(['business_id' => $business->id, 'status' => PaymentTransaction::STATUS_FAILED]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.finance.payments.retry', $transaction->id))
            ->assertSessionHasErrors('transaction');
    }

    public function test_platform_admin_without_payments_permission_is_forbidden(): void
    {
        $restrictedRole = PlatformRole::query()->create(['name' => 'Support', 'slug' => 'support-only', 'is_system' => false]);
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $restrictedRole->id]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.payments.index'))
            ->assertForbidden();
    }

    public function test_role_without_refund_permission_cannot_refund(): void
    {
        $role = PlatformRole::query()->create(['name' => 'Viewer', 'slug' => 'viewer-only', 'is_system' => false]);
        $role->permissions()->sync(
            \App\Domain\RBAC\Models\Permission::query()->whereIn('slug', ['payments.view', 'payments.manage'])->pluck('id'),
        );
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);
        [, $business] = $this->createOwnerWithBusiness();
        $transaction = PaymentTransaction::factory()->create(['business_id' => $business->id, 'status' => PaymentTransaction::STATUS_SUCCESSFUL]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.finance.payments.refund', $transaction->id), ['amount' => 1000])
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_payments(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.finance.payments.index'))
            ->assertRedirect(route('platform.login'));
    }
}
