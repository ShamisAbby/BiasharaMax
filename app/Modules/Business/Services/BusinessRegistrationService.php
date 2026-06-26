<?php

namespace App\Modules\Business\Services;

use App\Modules\Authentication\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\RBAC\Services\RoleProvisioningService;
use App\Modules\Subscription\Models\SubscriptionPlan;
use App\Modules\Subscription\Services\SubscriptionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
    ) {}

    /**
     * @param  array{
     *     owner_name: string,
     *     owner_email: string,
     *     owner_phone: ?string,
     *     password: string,
     *     business_name: string,
     *     business_type: string,
     *     business_phone: ?string,
     *     country: string,
     *     currency: string,
     *     subscription_plan_id: string,
     * }  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
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
                'role_id' => $ownerRole->getKey(),
                'branch_id' => $mainBranch->getKey(),
            ])->save();

            $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);
            $this->subscriptionService->startTrial($business, $plan);

            event(new Registered($owner));

            return $owner->refresh();
        });
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
