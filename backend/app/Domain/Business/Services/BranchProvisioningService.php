<?php

namespace App\Domain\Business\Services;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\Warehouse;

/**
 * Every business needs at least one branch and one warehouse to operate
 * (inventory, sales and purchasing all need a location to attach stock to).
 * This provisions both atomically as part of business registration so a
 * business is never left without a place to record stock against.
 */
class BranchProvisioningService
{
    public function provisionMainBranch(Business $business): Branch
    {
        $branch = Branch::query()->create([
            'business_id' => $business->getKey(),
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_main' => true,
            'phone' => $business->phone,
            'address' => $business->address,
            'city' => $business->city,
            'status' => 'active',
        ]);

        Warehouse::query()->create([
            'business_id' => $business->getKey(),
            'branch_id' => $branch->getKey(),
            'name' => 'Main Warehouse',
            'code' => 'MAIN-WH',
            'is_default' => true,
            'status' => 'active',
        ]);

        return $branch;
    }
}
