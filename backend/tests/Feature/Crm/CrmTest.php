<?php

namespace Tests\Feature\Crm;

use App\Domain\Authentication\Models\User;
use App\Domain\CRM\Models\CustomerGroup;
use App\Domain\CRM\Models\CustomerLoyaltyTransaction;
use App\Domain\CRM\Models\CustomerNote;
use App\Domain\CRM\Models\CustomerTag;
use App\Domain\RBAC\Models\Role;
use App\Domain\Sales\Models\Customer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_a_customer_group(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/crm/customer-groups', [
            'name' => 'Wholesale',
            'is_vip' => false,
            'discount_percentage' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_groups', [
            'business_id' => $business->id,
            'name' => 'Wholesale',
            'slug' => 'wholesale',
        ]);
    }

    public function test_owner_can_create_a_customer_tag(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/crm/customer-tags', [
            'name' => 'VIP',
            'color' => '#FFD700',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_tags', [
            'business_id' => $business->id,
            'name' => 'VIP',
            'slug' => 'vip',
        ]);
    }

    public function test_owner_can_add_and_delete_a_note_on_a_customer(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane']);

        $this->actingAs($owner)->post("/crm/customers/{$customer->id}/notes", [
            'body' => 'Called about a late delivery, resolved.',
        ])->assertSessionHasNoErrors();

        $note = CustomerNote::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame($owner->id, $note->created_by);

        $this->actingAs($owner)->delete("/crm/customers/{$customer->id}/notes/{$note->id}")
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted('customer_notes', ['id' => $note->id]);
    }

    public function test_owner_can_sync_tags_and_assign_a_group_to_a_customer(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane']);
        $tag = CustomerTag::create(['business_id' => $business->id, 'name' => 'VIP', 'slug' => 'vip']);
        $group = CustomerGroup::create(['business_id' => $business->id, 'name' => 'Wholesale', 'slug' => 'wholesale']);

        $this->actingAs($owner)->patch("/crm/customers/{$customer->id}/tags", [
            'tag_ids' => [$tag->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($customer->tags()->where('customer_tag_id', $tag->id)->exists());

        $this->actingAs($owner)->patch("/crm/customers/{$customer->id}/group", [
            'customer_group_id' => $group->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($group->id, $customer->refresh()->customer_group_id);
    }

    public function test_earning_and_redeeming_loyalty_points_updates_the_running_balance(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane']);

        $this->actingAs($owner)->post("/crm/customers/{$customer->id}/loyalty", [
            'type' => 'earn',
            'points' => 100,
        ])->assertSessionHasNoErrors();

        $this->assertSame(100, $customer->refresh()->loyalty_points);

        $this->actingAs($owner)->post("/crm/customers/{$customer->id}/loyalty", [
            'type' => 'redeem',
            'points' => 40,
        ])->assertSessionHasNoErrors();

        $this->assertSame(60, $customer->refresh()->loyalty_points);
        $this->assertSame(2, CustomerLoyaltyTransaction::query()->where('customer_id', $customer->id)->count());
    }

    public function test_redeeming_more_points_than_the_balance_fails_validation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'loyalty_points' => 10]);

        $this->actingAs($owner)->post("/crm/customers/{$customer->id}/loyalty", [
            'type' => 'redeem',
            'points' => 50,
        ])->assertSessionHasErrors('points');

        $this->assertSame(10, $customer->refresh()->loyalty_points);
    }

    public function test_vip_customer_count_reflects_group_assignment(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $vipGroup = CustomerGroup::create(['business_id' => $business->id, 'name' => 'VIP Tier', 'slug' => 'vip-tier', 'is_vip' => true]);
        Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_group_id' => $vipGroup->id]);
        Customer::create(['business_id' => $business->id, 'name' => 'John']);

        $summary = app(\App\Domain\CRM\Services\CrmDashboardService::class)->summary($business->id);

        $this->assertSame(2, $summary['total_customers']);
        $this->assertSame(1, $summary['vip_customers']);
    }

    public function test_employee_without_crm_manage_permission_cannot_add_a_note(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane']);

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post("/crm/customers/{$customer->id}/notes", [
            'body' => 'Should not be allowed.',
        ])->assertForbidden();

        $this->assertSame(0, CustomerNote::query()->count());
    }
}
