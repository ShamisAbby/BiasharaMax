<?php

namespace App\Http\Controllers\Api;

use App\Domain\Business\Models\Branch;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The branches and warehouses a till can sell from.
 *
 * This exists because the desktop app had no way to ask. Its warehouse
 * picker was listing raw UUIDs scraped out of synced inventory rows —
 * a cashier being asked to choose between
 * "019fd8c9-e7de-71c5-b22c-403067c85f36" and another one just like it.
 *
 * It also fixes a dead end at checkout: a sale needs a branch_id, which
 * the app could only get from the employee's own `branch_id`. That field
 * is optional when inviting staff, so anyone invited without one could
 * ring up a sale and then fail to complete it, with an error telling them
 * to sign out and pick a branch — which no screen offered. Warehouses are
 * returned nested under their branch so the till picks both at once.
 */
class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Tenant-scoped by the models\' BelongsToTenant trait, which is
        // guard-aware for sanctum — no explicit business_id filter needed
        // or wanted here.
        $branches = Branch::query()
            ->where('status', 'active')
            ->with(['warehouses' => fn ($q) => $q->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'city']);

        return response()->json([
            'data' => $branches->map(fn (Branch $branch): array => [
                'id' => $branch->getKey(),
                'name' => $branch->name,
                'code' => $branch->code,
                'city' => $branch->city,
                'warehouses' => $branch->warehouses->map(fn ($warehouse): array => [
                    'id' => $warehouse->getKey(),
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'is_default' => (bool) $warehouse->is_default,
                ])->values(),
            ])->values(),
            // The employee\'s own branch, so the till can preselect the
            // common case instead of making every cashier choose.
            'default_branch_id' => $request->user()->branch_id,
        ]);
    }
}
