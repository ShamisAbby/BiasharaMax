<?php

namespace App\Domain\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Http\Requests\LoyaltyTierStoreRequest;
use App\Domain\CRM\Http\Requests\LoyaltyTierUpdateRequest;
use App\Domain\CRM\Http\Resources\LoyaltyTierResource;
use App\Domain\CRM\Models\LoyaltyTier;
use App\Domain\CRM\Services\LoyaltyTierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyTierController extends Controller
{
    public function __construct(
        private readonly LoyaltyTierService $tierService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LoyaltyTier::class);

        $tiers = LoyaltyTier::query()
            ->withCount('customers')
            ->orderBy('minimum_spend')
            ->get();

        return Inertia::render('Crm/Loyalty/Tiers', [
            'tiers' => LoyaltyTierResource::collection($tiers),
        ]);
    }

    public function store(LoyaltyTierStoreRequest $request): RedirectResponse
    {
        $this->tierService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        return back()->with('status', 'loyalty-tier-created');
    }

    public function update(LoyaltyTierUpdateRequest $request, LoyaltyTier $tier): RedirectResponse
    {
        $this->tierService->update($tier, $request->validated());

        return back()->with('status', 'loyalty-tier-updated');
    }

    public function destroy(LoyaltyTier $tier): RedirectResponse
    {
        $this->authorize('delete', $tier);
        $this->tierService->delete($tier);

        return back()->with('status', 'loyalty-tier-deleted');
    }
}
