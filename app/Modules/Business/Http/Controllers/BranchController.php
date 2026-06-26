<?php

namespace App\Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Business\Http\Requests\BranchStoreRequest;
use App\Modules\Business\Http\Requests\BranchUpdateRequest;
use App\Modules\Business\Http\Resources\BranchResource;
use App\Modules\Business\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->where('business_id', $request->user()->business_id)
            ->withCount(['warehouses', 'employees'])
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        return Inertia::render('Settings/Branches', [
            'branches' => BranchResource::collection($branches),
        ]);
    }

    public function store(BranchStoreRequest $request): RedirectResponse
    {
        Branch::query()->create([
            'business_id' => $request->user()->business_id,
            ...$request->validated(),
        ]);

        return back()->with('status', 'branch-created');
    }

    public function update(BranchUpdateRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->validated());

        return back()->with('status', 'branch-updated');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        if ($branch->warehouses()->exists() || $branch->employees()->exists()) {
            return back()->withErrors([
                'branch' => 'Move or remove this branch\'s warehouses and employees before deleting it.',
            ]);
        }

        $branch->delete();

        return back()->with('status', 'branch-deleted');
    }
}
