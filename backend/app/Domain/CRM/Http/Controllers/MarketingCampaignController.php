<?php

namespace App\Domain\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Http\Requests\MarketingCampaignStoreRequest;
use App\Domain\CRM\Http\Requests\MarketingCampaignUpdateRequest;
use App\Domain\CRM\Http\Resources\MarketingCampaignResource;
use App\Domain\CRM\Models\CustomerTag;
use App\Domain\CRM\Models\LoyaltyTier;
use App\Domain\CRM\Models\MarketingCampaign;
use App\Domain\CRM\Services\CampaignAudienceService;
use App\Domain\CRM\Services\MarketingCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MarketingCampaignController extends Controller
{
    public function __construct(
        private readonly MarketingCampaignService $campaignService,
        private readonly CampaignAudienceService $audienceService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MarketingCampaign::class);

        $businessId = $request->user()->business_id;

        $campaigns = MarketingCampaign::query()
            ->where('business_id', $businessId)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Crm/Campaigns/Index', [
            'campaigns' => MarketingCampaignResource::collection($campaigns),
            'tags' => CustomerTag::query()->where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
            'tiers' => LoyaltyTier::query()->where('business_id', $businessId)->orderBy('minimum_spend')->get(['id', 'name']),
        ]);
    }

    public function show(MarketingCampaign $campaign): Response
    {
        $this->authorize('view', $campaign);

        $campaign->load(['recipients' => fn ($q) => $q->latest()->limit(50), 'recipients.customer:id,name']);

        return Inertia::render('Crm/Campaigns/Show', [
            'campaign' => new MarketingCampaignResource($campaign),
            'recipients' => $campaign->recipients->map(fn ($r) => [
                'id' => $r->id,
                'email' => $r->email,
                'customer_name' => $r->customer?->name,
                'status' => $r->status,
                'error_message' => $r->error_message,
            ]),
        ]);
    }

    public function store(MarketingCampaignStoreRequest $request): RedirectResponse
    {
        $campaign = $this->campaignService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('crm.campaigns.show', $campaign)->with('status', 'campaign-created');
    }

    public function update(MarketingCampaignUpdateRequest $request, MarketingCampaign $campaign): RedirectResponse
    {
        if ($campaign->status !== MarketingCampaign::STATUS_DRAFT) {
            throw ValidationException::withMessages(['name' => 'Only draft campaigns can be edited.']);
        }

        $audienceCount = $this->audienceService->count($campaign->business_id, $request->validated('segment_filters') ?? []);
        $campaign->update([...$request->validated(), 'audience_count' => $audienceCount]);

        return back()->with('status', 'campaign-updated');
    }

    public function send(MarketingCampaign $campaign): RedirectResponse
    {
        $this->authorize('send', $campaign);

        if ($campaign->status !== MarketingCampaign::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'This campaign has already been sent.']);
        }

        $this->campaignService->send($campaign);

        return back()->with('status', 'campaign-sent');
    }

    public function destroy(MarketingCampaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);
        $campaign->delete();

        return back()->with('status', 'campaign-deleted');
    }

    public function previewAudience(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', MarketingCampaign::class);

        $count = $this->audienceService->count($request->user()->business_id, $request->input('segment_filters', []));

        return response()->json(['audience_count' => $count]);
    }
}
