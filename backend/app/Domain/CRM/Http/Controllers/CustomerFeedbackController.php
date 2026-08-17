<?php

namespace App\Domain\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Http\Requests\CustomerFeedbackAssignRequest;
use App\Domain\CRM\Http\Requests\CustomerFeedbackReplyRequest;
use App\Domain\CRM\Http\Requests\CustomerFeedbackStatusRequest;
use App\Domain\CRM\Http\Requests\CustomerFeedbackStoreRequest;
use App\Domain\CRM\Http\Resources\CustomerFeedbackResource;
use App\Domain\CRM\Models\CustomerFeedback;
use App\Domain\CRM\Services\CustomerFeedbackService;
use App\Domain\CRM\Services\FeedbackDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerFeedbackController extends Controller
{
    public function __construct(
        private readonly CustomerFeedbackService $feedbackService,
    ) {}

    public function index(Request $request, FeedbackDashboardService $dashboardService): Response
    {
        $this->authorize('viewAny', CustomerFeedback::class);

        $businessId = $request->user()->business_id;

        $feedback = CustomerFeedback::query()
            ->with(['customer:id,name', 'assignedTo:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Crm/Feedback/Index', [
            'feedback' => CustomerFeedbackResource::collection($feedback),
            'summary' => $dashboardService->summary($businessId),
            'typeBreakdown' => $dashboardService->typeBreakdown($businessId),
            'filters' => $request->only(['status', 'type']),
        ]);
    }

    public function show(CustomerFeedback $feedback): Response
    {
        $this->authorize('view', $feedback);

        $feedback->load(['customer:id,name', 'assignedTo:id,name', 'replies.author:id,name']);

        return Inertia::render('Crm/Feedback/Show', [
            'feedback' => new CustomerFeedbackResource($feedback),
            'agents' => \App\Domain\Authentication\Models\User::query()
                ->where('business_id', $feedback->business_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(CustomerFeedbackStoreRequest $request): RedirectResponse
    {
        $this->feedbackService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'feedback-created');
    }

    public function reply(CustomerFeedbackReplyRequest $request, CustomerFeedback $feedback): RedirectResponse
    {
        $this->feedbackService->reply($feedback, $request->validated('body'), $request->user()->id);

        return back()->with('status', 'feedback-replied');
    }

    public function updateStatus(CustomerFeedbackStatusRequest $request, CustomerFeedback $feedback): RedirectResponse
    {
        $this->feedbackService->updateStatus($feedback, $request->validated('status'));

        return back()->with('status', 'feedback-status-updated');
    }

    public function assign(CustomerFeedbackAssignRequest $request, CustomerFeedback $feedback): RedirectResponse
    {
        $this->feedbackService->assign($feedback, $request->validated('assigned_to'));

        return back()->with('status', 'feedback-assigned');
    }
}
