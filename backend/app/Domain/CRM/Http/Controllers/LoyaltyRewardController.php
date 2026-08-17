<?php

namespace App\Domain\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Http\Requests\LoyaltyRewardStoreRequest;
use App\Domain\CRM\Http\Requests\LoyaltyRewardUpdateRequest;
use App\Domain\CRM\Http\Resources\LoyaltyRewardResource;
use App\Domain\CRM\Models\LoyaltyReward;
use App\Domain\CRM\Services\LoyaltyRewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyRewardController extends Controller
{
    public function __construct(
        private readonly LoyaltyRewardService $rewardService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LoyaltyReward::class);

        $rewards = LoyaltyReward::query()
            ->withCount('redemptions')
            ->orderBy('points_cost')
            ->get();

        return Inertia::render('Crm/Loyalty/Rewards', [
            'rewards' => LoyaltyRewardResource::collection($rewards),
        ]);
    }

    public function store(LoyaltyRewardStoreRequest $request): RedirectResponse
    {
        $this->rewardService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        return back()->with('status', 'loyalty-reward-created');
    }

    public function update(LoyaltyRewardUpdateRequest $request, LoyaltyReward $reward): RedirectResponse
    {
        $this->rewardService->update($reward, $request->validated());

        return back()->with('status', 'loyalty-reward-updated');
    }

    public function destroy(LoyaltyReward $reward): RedirectResponse
    {
        $this->authorize('delete', $reward);
        $this->rewardService->delete($reward);

        return back()->with('status', 'loyalty-reward-deleted');
    }
}
