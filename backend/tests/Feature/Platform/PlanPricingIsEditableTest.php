<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the operator edits is what the customer is quoted.
 *
 * `price` and `duration_months` were added by migration and never added to
 * the admin form, so the seeder was their only writer. An operator could
 * rename "3 Months" to "6 Months" and set a monthly price, and the renewal
 * page — which reads `price` and `duration_months` — would still charge
 * the three-month figure for a three-month term under a label promising
 * six. Both screens were internally consistent and disagreed with each
 * other.
 */
class PlanPricingIsEditableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, PlatformRoleSeeder::class, SubscriptionPlanSeeder::class]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '6 Months',
            'slug' => 'six-months',
            'type' => 'standard',
            'description' => 'Six months of full access.',
            'duration_months' => 6,
            'price' => 170000,
            'trial_days' => 30,
            'is_active' => true,
            'sort_order' => 2,
        ], $overrides);
    }

    public function test_an_admin_can_set_the_duration_and_price(): void
    {
        $admin = PlatformUser::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'quarterly')->first();

        $this->actingAs($admin, 'platform')
            ->patch(route('platform.subscriptions.plans.update', $plan), $this->payload([
                'slug' => 'quarterly',
            ]))
            ->assertSessionHasNoErrors();

        $fresh = $plan->fresh();

        $this->assertSame(6, $fresh->duration_months);
        $this->assertEquals(170000, (float) $fresh->price);
    }

    /**
     * The legacy columns must follow rather than be typed separately.
     * Four numbers maintained by hand is four chances to disagree.
     */
    public function test_the_legacy_monthly_price_is_derived(): void
    {
        $admin = PlatformUser::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'quarterly')->first();

        $this->actingAs($admin, 'platform')
            ->patch(route('platform.subscriptions.plans.update', $plan), $this->payload([
                'slug' => 'quarterly',
                'duration_months' => 6,
                'price' => 180000,
            ]));

        $this->assertEquals(30000, (float) $plan->fresh()->price_monthly);
    }

    /**
     * A plan with no length cannot be sold — the customer would be told
     * "null months of full access" and the term would be guessed at
     * activation.
     */
    public function test_a_plan_without_a_duration_is_rejected(): void
    {
        $admin = PlatformUser::factory()->create();

        $this->actingAs($admin, 'platform')
            ->post(route('platform.subscriptions.plans.store'), $this->payload([
                'duration_months' => null,
            ]))
            ->assertSessionHasErrors('duration_months');
    }

    public function test_the_price_the_admin_sets_is_the_price_the_customer_sees(): void
    {
        $admin = PlatformUser::factory()->create();
        $plan = SubscriptionPlan::query()->where('slug', 'yearly')->first();

        $this->actingAs($admin, 'platform')
            ->patch(route('platform.subscriptions.plans.update', $plan), $this->payload([
                'name' => '12 Months',
                'slug' => 'yearly',
                'duration_months' => 12,
                'price' => 400000,
            ]));

        // The renewal page reads exactly these two columns.
        $fresh = $plan->fresh();

        $this->assertEquals(400000, (float) $fresh->price);
        $this->assertSame(12, $fresh->duration_months);
    }
}
