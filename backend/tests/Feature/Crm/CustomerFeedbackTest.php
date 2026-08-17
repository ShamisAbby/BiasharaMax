<?php

namespace Tests\Feature\Crm;

use App\Domain\Authentication\Models\User;
use App\Domain\CRM\Models\CustomerFeedback;
use App\Domain\CRM\Models\CustomerFeedbackReply;
use App\Domain\RBAC\Models\Role;
use App\Domain\Sales\Models\Customer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class CustomerFeedbackTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_log_feedback_for_a_customer(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane']);

        $this->actingAs($owner)->post('/crm/feedback', [
            'customer_id' => $customer->id,
            'type' => 'complaint',
            'subject' => 'Late delivery',
            'body' => 'Order arrived two days late.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_feedback', [
            'business_id' => $business->id,
            'customer_id' => $customer->id,
            'type' => 'complaint',
            'status' => 'open',
        ]);
    }

    public function test_replying_to_feedback_creates_a_reply_and_moves_status_to_pending(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $feedback = CustomerFeedback::create([
            'business_id' => $business->id, 'type' => 'complaint', 'body' => 'Issue with my order', 'status' => 'open',
        ]);

        $this->actingAs($owner)->post("/crm/feedback/{$feedback->id}/replies", [
            'body' => 'Sorry about that, we are looking into it.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, CustomerFeedbackReply::query()->where('customer_feedback_id', $feedback->id)->count());
        $this->assertSame('pending', $feedback->refresh()->status);
    }

    public function test_owner_can_update_status_and_assign_feedback(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $feedback = CustomerFeedback::create([
            'business_id' => $business->id, 'type' => 'review', 'body' => 'Great service!', 'status' => 'open',
        ]);
        $agent = User::factory()->create(['business_id' => $business->id, 'role_id' => $owner->role_id]);

        $this->actingAs($owner)->patch("/crm/feedback/{$feedback->id}/assign", [
            'assigned_to' => $agent->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($agent->id, $feedback->refresh()->assigned_to);

        $this->actingAs($owner)->patch("/crm/feedback/{$feedback->id}/status", [
            'status' => 'resolved',
        ])->assertSessionHasNoErrors();

        $feedback->refresh();
        $this->assertSame('resolved', $feedback->status);
        $this->assertNotNull($feedback->resolved_at);
    }

    public function test_employee_without_crm_manage_permission_cannot_reply_to_feedback(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $feedback = CustomerFeedback::create([
            'business_id' => $business->id, 'type' => 'review', 'body' => 'Nice store', 'status' => 'open',
        ]);

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post("/crm/feedback/{$feedback->id}/replies", [
            'body' => 'Should not be allowed.',
        ])->assertForbidden();

        $this->assertSame(0, CustomerFeedbackReply::query()->count());
    }
}
