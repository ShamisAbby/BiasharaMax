<?php

namespace App\Domain\Business\Services;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\RBAC\Services\RoleProvisioningService;
use App\Domain\Subscription\Models\RegistrationCode;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates the single combined "Business Registration" flow: an owner
 * account, its business, the default role set, and the 30-day trial
 * subscription are all created atomically. If any step fails, nothing is
 * persisted, so the platform never ends up with a business stuck in an
 * inconsistent state (e.g. a business with no owner, or no roles).
 */
class BusinessRegistrationService
{
    public function __construct(
        private readonly RoleProvisioningService $roleProvisioningService,
        private readonly SubscriptionService $subscriptionService,
        private readonly BranchProvisioningService $branchProvisioningService,
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {}

    /**
     * @param  array{
     *     owner_name: string,
     *     owner_email: string,
     *     owner_phone: ?string,
     *     password: string,
     *     business_name: string,
     *     business_type: string,
     *     // a BusinessType.slug value, validated by BusinessRegistrationRequest
     *     business_phone: ?string,
     *     country: string,
     *     currency: string,
     *     subscription_plan_id: string,
     * }  $data
     */
    public function register(array $data): User
    {
        $owner = DB::transaction(function () use ($data) {
            $owner = User::query()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'] ?? null,
                'password' => Hash::make($data['password']),
                'status' => 'active',
            ]);

            $business = Business::query()->create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'business_type' => $data['business_type'],
                'business_type_id' => BusinessType::query()->where('slug', $data['business_type'])->value('id'),
                'email' => $data['owner_email'],
                'phone' => $data['business_phone'] ?? null,
                'country' => $data['country'],
                'currency' => $data['currency'],
                'owner_id' => $owner->getKey(),
                'status' => Business::STATUS_TRIAL,
                'created_by' => $owner->getKey(),
                'updated_by' => $owner->getKey(),
            ]);

            $this->roleProvisioningService->provisionDefaultRoles($business);
            $ownerRole = $this->roleProvisioningService->ownerRoleFor($business);
            $mainBranch = $this->branchProvisioningService->provisionMainBranch($business);

            $owner->forceFill([
                'business_id' => $business->getKey(),
                // Legacy single-role column, still written so anything
                // reading it keeps working. Authorization uses the pivot
                // synced immediately below.
                'role_id' => $ownerRole->getKey(),
                'branch_id' => $mainBranch->getKey(),
            ])->save();

            $owner->roles()->sync([$ownerRole->getKey()]);

            if (! empty($data['registration_code'])) {
                /** @var RegistrationCode $regCode */
                $regCode = RegistrationCode::query()
                    ->with('plan')
                    ->where('code', $data['registration_code'])
                    ->firstOrFail();

                $plan = $regCode->plan;

                $this->subscriptionService->startSubscription(
                    $business,
                    $plan,
                    $regCode->billing_cycle,
                    $regCode->duration_months,
                );

                $regCode->update([
                    'status'  => RegistrationCode::STATUS_USED,
                    'used_by' => $business->getKey(),
                    'used_at' => now(),
                ]);
            } elseif (! empty($data['start_trial'])) {
                // The trial still needs a plan row to point at, because
                // `subscriptions.subscription_plan_id` is a foreign key.
                // Which one no longer affects what the customer gets — the
                // three plans differ only in length and price — so this
                // takes the shortest, and the choice is recorded here so
                // nobody later reads meaning into it.
                $plan = SubscriptionPlan::query()
                    ->where('is_active', true)
                    ->orderBy('duration_months')
                    ->firstOrFail();

                $this->subscriptionService->startTrial($business, $plan);
            } else {
                $plan = SubscriptionPlan::query()
                    ->where('is_active', true)
                    ->findOrFail($data['subscription_plan_id']);

                // Created unpaid and locked. Access opens when the payment
                // is confirmed, not when the form is submitted.
                //
                // This is the branch that used to call `startTrial()` no
                // matter what the customer picked — so choosing a paid
                // plan handed out 30 free days and recorded a trial. It
                // looked correct from both sides: the customer got in, and
                // the database said "trialing" as though that had been the
                // request.
                $this->subscriptionService->startPendingPayment($business, $plan);
            }

            $this->chartOfAccountsService->seedDefaults($business->getKey());

            return $owner->refresh();
        });

        // ------------------------------------------------------------------
        // Outside the transaction, and non-fatal.
        //
        // `User` implements MustVerifyEmail, so this event reaches
        // Laravel's SendEmailVerificationNotification listener, and the
        // VerifyEmail notification it sends is not queued — it opens an
        // SMTP connection there and then. Inside the transaction that made
        // the mail server a participant in the write: a wrong password or
        // an unreachable host threw, the transaction rolled back, and the
        // owner saw a bare 500 having successfully created nothing.
        //
        // A registration that has already been committed must not be
        // undone by a mail failure. So the event fires after commit, and a
        // failure here is logged rather than raised — the account exists
        // and the person can request a new verification link. The failure
        // still has to be recorded somewhere, because the alternative is a
        // silent no-send that looks identical to a working system.
        // ------------------------------------------------------------------
        try {
            event(new Registered($owner));
        } catch (\Throwable $e) {
            Log::error('Registration succeeded but the verification email failed to send.', [
                'user_id' => $owner->getKey(),
                'email' => $owner->email,
                'exception' => $e->getMessage(),
            ]);
        }

        return $owner;
    }

    private function uniqueSlug(string $businessName): string
    {
        $base = Str::slug($businessName);
        $slug = $base;
        $suffix = 1;

        while (Business::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
