<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Exceptions\PlanLimitExceededException;

/**
 * Enforces the per-resource limits configured on a business's effective
 * plan (catalog plan, or a negotiated custom override). Call
 * ensureCanAdd() before creating the resource; a null limit means
 * unlimited.
 */
class SubscriptionLimitService
{
    public function ensureCanAdd(Business $business, string $resource): void
    {
        $subscription = $business->subscription;

        if ($subscription === null) {
            return;
        }

        $limits = $subscription->effectiveLimits();
        $limitKey = "max_{$resource}";

        if (! array_key_exists($limitKey, $limits) || $limits[$limitKey] === null) {
            return;
        }

        $limit = $limits[$limitKey];
        $current = $this->currentCount($business, $resource);

        if ($current >= $limit) {
            throw PlanLimitExceededException::forResource($resource, $limit);
        }
    }

    private function currentCount(Business $business, string $resource): int
    {
        return match ($resource) {
            'users' => $business->users()->count(),
            'employees' => $business->users()->where('id', '!=', $business->owner_id)->count(),
            'branches' => $business->branches()->count(),
            'warehouses' => $business->warehouses()->count(),
            'products' => $business->products()->count(),
            default => throw new \InvalidArgumentException("Unknown limited resource [{$resource}]."),
        };
    }
}
