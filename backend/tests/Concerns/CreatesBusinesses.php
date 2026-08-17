<?php

namespace Tests\Concerns;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\BusinessRegistrationService;
use App\Domain\Subscription\Models\SubscriptionPlan;

/**
 * Shared by every test that needs a fully registered, email-verified
 * business owner — the same atomic registration flow real users go
 * through, rather than hand-assembling a business/owner/role/subscription
 * combination that could silently drift from what the app actually does.
 */
trait CreatesBusinesses
{
    /**
     * @return array{0: User, 1: Business}
     */
    protected function createOwnerWithBusiness(): array
    {
        $plan = SubscriptionPlan::factory()->create();

        $owner = app(BusinessRegistrationService::class)->register([
            'owner_name' => 'Owner '.fake()->unique()->numerify('###'),
            'owner_email' => fake()->unique()->safeEmail(),
            'owner_phone' => null,
            'password' => 'Password123!',
            'business_name' => fake()->unique()->company(),
            'business_type' => 'retail',
            'business_phone' => null,
            'country' => 'TZ',
            'currency' => 'TZS',
            'subscription_plan_id' => $plan->id,
        ]);

        $owner->forceFill(['email_verified_at' => now()])->save();

        return [$owner, $owner->business];
    }
}
