<?php

namespace App\Domain\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Purchasing\Http\Requests\SupplierStoreRequest;
use App\Domain\Purchasing\Http\Requests\SupplierUpdateRequest;
use App\Domain\Purchasing\Http\Resources\SupplierResource;
use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = Supplier::query()
            ->where('business_id', $request->user()->business_id)
            ->withCount('defaultForProducts')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Suppliers', [
            'suppliers' => SupplierResource::collection($suppliers),
        ]);
    }

    public function store(SupplierStoreRequest $request): RedirectResponse
    {
        Supplier::create([
            'business_id' => $request->user()->business_id,
            ...$request->validated(),
        ]);

        return back()->with('status', 'supplier-created');
    }

    public function update(SupplierUpdateRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('status', 'supplier-updated');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->defaultForProducts()->exists()) {
            return back()->withErrors(['supplier' => 'Reassign products using this as their default supplier before deleting it.']);
        }

        $supplier->delete();

        return back()->with('status', 'supplier-deleted');
    }
}
