<?php

namespace Tests\Feature\Crm;

use App\Domain\Authentication\Models\User;
use App\Domain\CRM\Models\CampaignRecipient;
use App\Domain\CRM\Models\CustomerTag;
use App\Domain\CRM\Models\MarketingCampaign;
use App\Domain\RBAC\Models\Role;
use App\Domain\Sales\Models\Customer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class MarketingCampaignTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_a_draft_campaign_with_an_audience_count(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $tag = CustomerTag::create(['business_id' => $business->id, 'name' => 'VIP', 'slug' => 'vip']);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'email' => 'jane@example.com']);
        $customer->tags()->attach($tag->id);
        Customer::create(['business_id' => $business->id, 'name' => 'No Tag', 'email' => 'notag@example.com']);

        $this->actingAs($owner)->post('/crm/campaigns', [
            'name' => 'VIP Promo',
            'subject' => 'A special offer for you',
            'body' => 'Enjoy 10% off this week.',
            'segment_filters' => ['tag_ids' => [$tag->id]],
        ])->assertSessionHasNoErrors();

        $campaign = MarketingCampaign::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame('draft', $campaign->status);
        $this->assertSame(1, $campaign->audience_count);
    }

    public function test_sending_a_campaign_emails_the_real_matching_audience_and_records_recipients(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        $tag = CustomerTag::create(['business_id' => $business->id, 'name' => 'VIP', 'slug' => 'vip']);
        $matching = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'email' => 'jane@example.com']);
        $matching->tags()->attach($tag->id);
        Customer::create(['business_id' => $business->id, 'name' => 'No Tag', 'email' => 'notag@example.com']);

        $campaign = MarketingCampaign::create([
            'business_id' => $business->id, 'name' => 'VIP Promo', 'subject' => 'Hello', 'body' => 'Body text',
            'segment_filters' => ['tag_ids' => [$tag->id]],
        ]);

        $this->actingAs($owner)->post("/crm/campaigns/{$campaign->id}/send")->assertSessionHasNoErrors();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame(1, CampaignRecipient::query()->where('marketing_campaign_id', $campaign->id)->count());
        $this->assertDatabaseHas('campaign_recipients', [
            'marketing_campaign_id' => $campaign->id,
            'customer_id' => $matching->id,
            'email' => 'jane@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_a_sent_campaign_cannot_be_sent_again(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        $campaign = MarketingCampaign::create([
            'business_id' => $business->id, 'name' => 'Promo', 'subject' => 'Hello', 'body' => 'Body',
            'status' => MarketingCampaign::STATUS_SENT,
        ]);

        $this->actingAs($owner)->post("/crm/campaigns/{$campaign->id}/send")->assertSessionHasErrors('status');
    }

    public function test_audience_excludes_customers_without_an_email(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        Customer::create(['business_id' => $business->id, 'name' => 'No Email']);
        Customer::create(['business_id' => $business->id, 'name' => 'Has Email', 'email' => 'has@example.com']);

        $this->actingAs($owner)->post('/crm/campaigns', [
            'name' => 'Broad Promo',
            'subject' => 'Hello',
            'body' => 'Body',
        ])->assertSessionHasNoErrors();

        $campaign = MarketingCampaign::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame(1, $campaign->audience_count);
    }

    public function test_employee_without_crm_manage_permission_cannot_create_a_campaign(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post('/crm/campaigns', [
            'name' => 'Promo',
            'subject' => 'Hello',
            'body' => 'Body',
        ])->assertForbidden();

        $this->assertSame(0, MarketingCampaign::query()->count());
    }
}
