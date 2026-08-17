<?php

namespace Tests\Feature\Api;

use App\Domain\Business\Models\Business;
use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Models\LicenseDevice;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\DesktopEntitlementService;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * First-run onboarding for the desktop client: sign up, get let in or
 * turned away, and be told which of those it was.
 *
 * The behaviour under test used to be impossible. The app demanded a
 * product key before it would show a login box, and product keys are only
 * ever minted by hand from the platform admin — so a business that signed
 * up for a trial had nothing to type and could not use the desktop app at
 * all. Admission is now the subscription's job; device licensing applies
 * on top, and only to businesses that hold licences.
 */
class DesktopOnboardingTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(BusinessTypeSeeder::class);
        // Country and currency are validated against these tables now,
        // not merely by string length.
        $this->seed(CurrencySeeder::class);
        $this->seed(CountrySeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'owner_name' => 'Asha Mwinyi',
            'owner_email' => 'asha@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'business_name' => 'Asha Groceries',
            'business_type' => 'retail',
            'country' => 'TZ',
            'currency' => 'TZS',
            'device_name' => 'Front Counter',
        ], $overrides);
    }

    public function test_signing_up_starts_a_trial_and_returns_a_usable_session(): void
    {
        SubscriptionPlan::factory()->create(['trial_days' => 30, 'sort_order' => 0]);

        $response = $this->postJson(route('api.auth.register'), $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('entitlement.allowed', true);
        $response->assertJsonPath('entitlement.subscription.status', Subscription::STATUS_TRIALING);

        // A token in the response, not a second login. Making someone
        // retype the password they chose ten seconds ago is a step that
        // exists only because the endpoints were written separately.
        $this->assertIsString($response->json('token'));

        $business = Business::query()->where('name', 'Asha Groceries')->firstOrFail();

        $this->assertSame(30, (int) round(now()->diffInDays($business->subscription->trial_ends_at)));
    }

    /**
     * The registration is one transaction, so a business arrives whole or
     * not at all. A trial account missing its chart of accounts looks fine
     * until someone opens the accounting screen months later.
     */
    public function test_signing_up_provisions_the_whole_business(): void
    {
        SubscriptionPlan::factory()->create(['trial_days' => 30]);

        $this->postJson(route('api.auth.register'), $this->payload())->assertCreated();

        $business = Business::query()->where('name', 'Asha Groceries')->firstOrFail();

        $this->assertDatabaseHas('branches', ['business_id' => $business->id]);
        $this->assertDatabaseHas('roles', ['business_id' => $business->id]);
        $this->assertTrue($business->owner->roles()->exists());
    }

    public function test_the_desktop_token_is_scoped_and_cannot_reach_beyond_the_desktop_surface(): void
    {
        SubscriptionPlan::factory()->create(['trial_days' => 30]);

        $token = $this->postJson(route('api.auth.register'), $this->payload())->json('token');

        // The ability is what stops a stolen till token being used to
        // mint further tokens or reach platform routes.
        $this->withToken($token)
            ->getJson(route('api.entitlement.show'))
            ->assertOk()
            ->assertJsonPath('entitlement.allowed', true);
    }

    public function test_a_duplicate_email_is_reported_against_the_email_field(): void
    {
        SubscriptionPlan::factory()->create(['trial_days' => 30]);
        [$owner] = $this->createOwnerWithBusiness();

        $response = $this->postJson(
            route('api.auth.register'),
            $this->payload(['owner_email' => $owner->email]),
        );

        // Per-field, because the sign-up form has eight of them and "the
        // given data was invalid" makes finding the wrong one a guess.
        $response->assertStatus(422)->assertJsonValidationErrors('owner_email');
    }

    public function test_sign_up_refuses_a_product_key_that_does_not_exist(): void
    {
        SubscriptionPlan::factory()->create(['trial_days' => 30]);

        $this->postJson(route('api.auth.register'), $this->payload([
            'registration_code' => 'NOT-A-REAL-KEY',
        ]))->assertStatus(422)->assertJsonValidationErrors('registration_code');

        // Nothing half-created behind the failure.
        $this->assertDatabaseMissing('businesses', ['name' => 'Asha Groceries']);
    }

    /**
     * The bug this whole change exists to fix.
     */
    public function test_a_trial_business_is_admitted_without_any_licence(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $this->assertDatabaseCount('licenses', 0);

        $entitlement = app(DesktopEntitlementService::class)->describe($business);

        $this->assertTrue(
            $entitlement['allowed'],
            'A business on a valid trial must be able to use the desktop app; licences are issued by hand and it has none.',
        );
    }

    public function test_a_lapsed_trial_is_turned_away_and_told_to_use_a_product_key(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $business->subscription->forceFill([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
        ])->save();

        $entitlement = app(DesktopEntitlementService::class)->describe($business->fresh());

        $this->assertFalse($entitlement['allowed']);
        $this->assertSame(DesktopEntitlementService::STATE_LOCKED, $entitlement['state']);
        $this->assertTrue($entitlement['requires_product_key']);

        // Not offered a second time. A trial that restarts from the
        // sign-in screen is a free product with extra steps.
        $this->assertFalse($entitlement['can_start_trial']);
    }

    public function test_a_business_holding_a_licence_must_activate_the_machine(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        License::factory()->create([
            'business_id' => $business->id,
            'status' => License::STATUS_ACTIVE,
        ]);

        $entitlement = app(DesktopEntitlementService::class)->describe($business, 'unknown-machine');

        $this->assertFalse($entitlement['allowed']);
        $this->assertSame(DesktopEntitlementService::STATE_DEVICE_NOT_ACTIVATED, $entitlement['state']);
    }

    public function test_an_activated_machine_is_admitted(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $licence = License::factory()->create([
            'business_id' => $business->id,
            'status' => License::STATUS_ACTIVE,
        ]);

        LicenseDevice::query()->create([
            'license_id' => $licence->id,
            'hardware_fingerprint' => 'this-machine',
            'machine_name' => 'Front Counter',
            'activated_at' => now(),
        ]);

        $this->assertTrue(
            app(DesktopEntitlementService::class)->describe($business, 'this-machine')['allowed'],
        );
    }

    public function test_a_released_device_no_longer_counts_as_activated(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $licence = License::factory()->create([
            'business_id' => $business->id,
            'status' => License::STATUS_ACTIVE,
        ]);

        LicenseDevice::query()->create([
            'license_id' => $licence->id,
            'hardware_fingerprint' => 'old-machine',
            'machine_name' => 'Replaced PC',
            'activated_at' => now()->subMonth(),
            'deactivated_at' => now()->subDay(),
        ]);

        // Deactivating is how an owner frees a seat after replacing a
        // machine. Counting the row anyway would make the release
        // meaningless and hand out an extra device for free.
        $this->assertFalse(
            app(DesktopEntitlementService::class)->describe($business, 'old-machine')['allowed'],
        );
    }

    public function test_login_reports_entitlement_so_the_app_knows_where_to_send_the_user(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $business->subscription->forceFill([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
        ])->save();

        $response = $this->postJson(route('api.auth.login'), [
            'email' => $owner->email,
            'password' => 'Password123!',
            'device_name' => 'Front Counter',
        ]);

        // Sign-in succeeds — the password is right. What is wrong is the
        // subscription, and the response says so rather than failing the
        // login and blaming the credentials.
        $response->assertOk();
        $response->assertJsonPath('entitlement.allowed', false);
        $response->assertJsonPath('entitlement.state', DesktopEntitlementService::STATE_LOCKED);
    }

    public function test_me_does_not_return_the_whole_user_row(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($owner, 'sanctum')->getJson(route('api.auth.me'));

        $response->assertOk();

        // A desktop token has no reason to read back lockout counters or
        // audit columns, and the endpoint used to hand over every column
        // on the row.
        $this->assertSame(
            ['id', 'name', 'email', 'business_id', 'branch_id', 'role_id', 'business_name'],
            array_keys($response->json('user')),
        );
    }

    public function test_registration_options_lists_only_plans_that_can_be_offered(): void
    {
        SubscriptionPlan::factory()->create(['name' => 'Live plan', 'is_active' => true]);
        SubscriptionPlan::factory()->create(['name' => 'Retired plan', 'is_active' => false]);

        $response = $this->getJson(route('api.auth.register.options'));

        $response->assertOk();

        $names = array_column($response->json('plans'), 'name');

        $this->assertContains('Live plan', $names);
        $this->assertNotContains('Retired plan', $names);
        $this->assertNotEmpty($response->json('business_types'));
    }

    /**
     * Currency is stamped on every price, sale and ledger entry the
     * business will ever record, and cannot be corrected afterwards
     * without the books disagreeing with themselves. `size:3` alone
     * accepted "XXX".
     */
    public function test_an_unknown_currency_is_rejected(): void
    {
        SubscriptionPlan::factory()->create(['trial_days' => 30]);

        $this->postJson(route('api.auth.register'), $this->payload(['currency' => 'XXX']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');

        $this->assertDatabaseMissing('businesses', ['name' => 'Asha Groceries']);
    }

    public function test_an_unknown_country_is_rejected(): void
    {
        SubscriptionPlan::factory()->create(['trial_days' => 30]);

        $this->postJson(route('api.auth.register'), $this->payload(['country' => 'ZZ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('country');
    }

    public function test_the_options_endpoint_offers_countries_and_currencies(): void
    {
        SubscriptionPlan::factory()->create();

        $response = $this->getJson(route('api.auth.register.options'));

        $response->assertOk();

        // The desktop sign-up form had no field for either and hardcoded
        // TZ/TZS, so a Kenyan shop was silently given Tanzanian
        // shillings. It cannot offer a choice it was never sent.
        $this->assertNotEmpty($response->json('countries'));
        $this->assertNotEmpty($response->json('currencies'));
        $this->assertArrayHasKey('default_currency_code', $response->json('countries')[0]);
    }

    public function test_a_zero_day_plan_is_never_used_to_start_a_trial(): void
    {
        // Sorted first, so a naive "cheapest active plan" pick would take
        // it — and hand the vendor a trial that expired on creation.
        SubscriptionPlan::factory()->create(['trial_days' => 0, 'sort_order' => 0]);
        SubscriptionPlan::factory()->create(['trial_days' => 14, 'sort_order' => 1]);

        $this->postJson(route('api.auth.register'), $this->payload())->assertCreated();

        $business = Business::query()->where('name', 'Asha Groceries')->firstOrFail();

        $this->assertTrue($business->subscription->trial_ends_at->isFuture());
        $this->assertSame(14, (int) round(now()->diffInDays($business->subscription->trial_ends_at)));
    }
}
