<?php

namespace App\Http\Controllers\Api;

use App\Domain\Business\Models\BusinessType;
use App\Domain\Business\Services\BusinessRegistrationService;
use App\Domain\Localization\Models\Country;
use App\Domain\Localization\Models\Currency;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\DesktopEntitlementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DesktopRegistrationRequest;
use Illuminate\Http\JsonResponse;

/**
 * Sign-up for the desktop client.
 *
 * It delegates to the same BusinessRegistrationService the web sign-up
 * uses rather than creating rows itself. That service does considerably
 * more than insert a user — default roles, the main branch, a chart of
 * accounts, the subscription, all in one transaction. A second
 * implementation here would drift from it, and businesses created from
 * the desktop would be subtly broken in ways nobody noticed until
 * someone opened the accounting screen.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private readonly BusinessRegistrationService $registrationService,
        private readonly DesktopEntitlementService $entitlements,
    ) {}

    /**
     * Everything the sign-up form needs to be filled in correctly.
     *
     * One request rather than three, because a form that cannot be
     * submitted until it is complete should not depend on three separate
     * round trips, each able to fail on its own over a bad connection.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'description', 'price_monthly', 'trial_days', 'features']),
            'business_types' => BusinessType::query()
                ->where('status', BusinessType::STATUS_ACTIVE)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug']),

            // Country carries `default_currency_code` so choosing one can
            // fill in the other. Currency is the field that matters: it is
            // stamped on every price, sale and ledger entry the business
            // will ever record, and it is not something a vendor can
            // correct later without the books disagreeing with themselves.
            'countries' => Country::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['code', 'name', 'default_currency_code']),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name', 'symbol']),
        ]);
    }

    /**
     * Creates the business and signs the new owner straight in.
     *
     * Returning a token is the point: asking someone to register and then
     * immediately retype the password they just chose is a step that
     * exists only because the two endpoints were written separately.
     */
    public function store(DesktopRegistrationRequest $request): JsonResponse
    {
        $owner = $this->registrationService->register($request->registrationPayload());

        // Same scoped ability as AuthController::login. A leaked desktop
        // token can act for this business but cannot mint further tokens
        // or reach platform routes.
        $token = $owner->createToken(
            $request->string('device_name')->value() ?: 'Desktop',
            ['desktop'],
        );

        $owner->loadMissing('business.subscription.plan');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'business_id' => $owner->business_id,
                'branch_id' => $owner->branch_id,
                'role_id' => $owner->role_id,
            ],
            'entitlement' => $this->entitlements->describe($owner->business),
        ], 201);
    }
}
