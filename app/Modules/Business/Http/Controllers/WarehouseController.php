<?php

namespace App\Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Business\Http\Requests\WarehouseStoreRequest;
use App\Modules\Business\Http\Requests\WarehouseUpdateRequest;
use App\Modules\Business\Http\Resources\BranchResource;
use App\Modules\Business\Http\Resources\WarehouseResource;
use App\Modules\Business\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Warehouse::class);

        $business = $request->user()->business;

        return Inertia::render('Settings/Warehouses', [
            'warehouses' => WarehouseResource::collection(
                $business->warehouses()->with('branch')->orderBy('name')->get()
            ),
            'branches' => BranchResource::collection(
                $business->branches()->where('status', 'active')->orderBy('name')->get()
            ),
        ]);
    }

    public function store(WarehouseStoreRequest $request): RedirectResponse
    {
        Warehouse::query()->create([
            'business_id' => $request->user()->business_id,
            ...$request->validated(),
        ]);

        return back()->with('status', 'warehouse-created');
    }

    public function update(WarehouseUpdateRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        return back()->with('status', 'warehouse-updated');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('delete', $warehouse);

        $warehouse->delete();

        return back()->with('status', 'warehouse-deleted');
    }
}
