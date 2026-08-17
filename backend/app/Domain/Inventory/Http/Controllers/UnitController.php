<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Http\Requests\UnitStoreRequest;
use App\Domain\Inventory\Http\Requests\UnitUpdateRequest;
use App\Domain\Inventory\Http\Resources\UnitResource;
use App\Domain\Inventory\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Unit::class);

        $units = Unit::query()
            ->where('business_id', $request->user()->business_id)
            ->with('baseUnit')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Units', [
            'units' => UnitResource::collection($units),
        ]);
    }

    public function store(UnitStoreRequest $request): RedirectResponse
    {
        Unit::create([
            'business_id' => $request->user()->business_id,
            ...$request->validated(),
        ]);

        return back()->with('status', 'unit-created');
    }

    public function update(UnitUpdateRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validated());

        return back()->with('status', 'unit-updated');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        if ($unit->products()->exists() || $unit->derivedUnits()->exists()) {
            return back()->withErrors(['unit' => 'Reassign products and derived units before deleting this unit.']);
        }

        $unit->delete();

        return back()->with('status', 'unit-deleted');
    }
}
