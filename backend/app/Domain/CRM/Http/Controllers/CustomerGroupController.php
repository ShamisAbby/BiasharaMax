<?php

namespace App\Domain\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Http\Requests\CustomerGroupStoreRequest;
use App\Domain\CRM\Http\Requests\CustomerGroupUpdateRequest;
use App\Domain\CRM\Http\Resources\CustomerGroupResource;
use App\Domain\CRM\Models\CustomerGroup;
use App\Domain\CRM\Services\CustomerGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerGroupController extends Controller
{
    public function __construct(
        private readonly CustomerGroupService $groupService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerGroup::class);

        $groups = CustomerGroup::query()
            ->withCount('customers')
            ->orderBy('name')
            ->get();

        return Inertia::render('Crm/Groups/Index', [
            'groups' => CustomerGroupResource::collection($groups),
        ]);
    }

    public function store(CustomerGroupStoreRequest $request): RedirectResponse
    {
        $this->groupService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        return back()->with('status', 'customer-group-created');
    }

    public function update(CustomerGroupUpdateRequest $request, CustomerGroup $group): RedirectResponse
    {
        $this->groupService->update($group, $request->validated());

        return back()->with('status', 'customer-group-updated');
    }

    public function destroy(CustomerGroup $group): RedirectResponse
    {
        $this->authorize('delete', $group);
        $this->groupService->delete($group);

        return back()->with('status', 'customer-group-deleted');
    }
}
