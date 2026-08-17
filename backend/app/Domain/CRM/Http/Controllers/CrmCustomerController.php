<?php

namespace App\Domain\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Exceptions\LoyaltyPointsException;
use App\Domain\CRM\Exceptions\LoyaltyRewardException;
use App\Domain\CRM\Http\Requests\CustomerGroupAssignRequest;
use App\Domain\CRM\Http\Requests\CustomerLoyaltyAdjustRequest;
use App\Domain\CRM\Http\Requests\CustomerNoteStoreRequest;
use App\Domain\CRM\Http\Requests\CustomerTagSyncRequest;
use App\Domain\CRM\Http\Requests\LoyaltyRewardRedeemRequest;
use App\Domain\CRM\Http\Resources\CustomerCrmProfileResource;
use App\Domain\CRM\Http\Resources\CustomerLoyaltyTransactionResource;
use App\Domain\CRM\Http\Resources\CustomerNoteResource;
use App\Domain\CRM\Http\Resources\CustomerTagResource;
use App\Domain\CRM\Models\CustomerGroup;
use App\Domain\CRM\Models\CustomerNote;
use App\Domain\CRM\Models\CustomerTag;
use App\Domain\CRM\Models\LoyaltyReward;
use App\Domain\CRM\Services\CustomerLoyaltyService;
use App\Domain\CRM\Services\CustomerNoteService;
use App\Domain\CRM\Services\LoyaltyRewardService;
use App\Domain\Sales\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CrmCustomerController extends Controller
{
    public function __construct(
        private readonly CustomerNoteService $noteService,
        private readonly CustomerLoyaltyService $loyaltyService,
        private readonly LoyaltyRewardService $rewardService,
    ) {}

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $customer->load([
            'group',
            'loyaltyTier',
            'tags',
            'crmNotes.author:id,name',
            'loyaltyTransactions',
            'sales' => fn ($query) => $query->latest()->limit(10),
            'debtTransactions' => fn ($query) => $query->limit(10),
        ]);

        return Inertia::render('Crm/Customers/Show', [
            'customer' => new CustomerCrmProfileResource($customer),
            'notes' => CustomerNoteResource::collection($customer->crmNotes),
            'loyaltyTransactions' => CustomerLoyaltyTransactionResource::collection($customer->loyaltyTransactions),
            'tags' => CustomerTagResource::collection(CustomerTag::query()->orderBy('name')->get()),
            'groups' => CustomerGroup::query()->orderBy('name')->get(['id', 'name', 'is_vip']),
            'availableRewards' => LoyaltyReward::query()->where('is_active', true)->orderBy('points_cost')->get(['id', 'name', 'points_cost', 'stock_quantity']),
        ]);
    }

    public function card(Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $customer->load('loyaltyTier');

        return Inertia::render('Crm/Customers/Card', [
            'customer' => new CustomerCrmProfileResource($customer),
        ]);
    }

    public function storeNote(CustomerNoteStoreRequest $request, Customer $customer): RedirectResponse
    {
        $this->noteService->create([
            ...$request->validated(),
            'business_id' => $customer->business_id,
            'customer_id' => $customer->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'note-added');
    }

    public function destroyNote(Customer $customer, CustomerNote $note): RedirectResponse
    {
        $this->authorize('delete', $note);
        $this->noteService->delete($note);

        return back()->with('status', 'note-deleted');
    }

    public function syncTags(CustomerTagSyncRequest $request, Customer $customer): RedirectResponse
    {
        $customer->tags()->sync($request->validated('tag_ids', []));

        return back()->with('status', 'tags-updated');
    }

    public function assignGroup(CustomerGroupAssignRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update(['customer_group_id' => $request->validated('customer_group_id')]);

        return back()->with('status', 'group-updated');
    }

    public function adjustLoyalty(CustomerLoyaltyAdjustRequest $request, Customer $customer): RedirectResponse
    {
        $createdBy = $request->user()->id;
        $points = $request->validated('points');
        $notes = $request->validated('notes');

        try {
            match ($request->validated('type')) {
                'earn' => $this->loyaltyService->earn($customer, $points, $notes, $createdBy),
                'redeem' => $this->loyaltyService->redeem($customer, $points, $notes, $createdBy),
                'adjustment' => $this->loyaltyService->adjust($customer, $points, $notes, $createdBy),
            };
        } catch (LoyaltyPointsException $e) {
            throw ValidationException::withMessages(['points' => $e->getMessage()]);
        }

        return back()->with('status', 'loyalty-adjusted');
    }

    public function redeemReward(LoyaltyRewardRedeemRequest $request, Customer $customer): RedirectResponse
    {
        $reward = LoyaltyReward::query()->findOrFail($request->validated('loyalty_reward_id'));

        try {
            $this->rewardService->redeem($customer, $reward, $request->user()->id);
        } catch (LoyaltyPointsException|LoyaltyRewardException $e) {
            throw ValidationException::withMessages(['loyalty_reward_id' => $e->getMessage()]);
        }

        return back()->with('status', 'reward-redeemed');
    }
}
