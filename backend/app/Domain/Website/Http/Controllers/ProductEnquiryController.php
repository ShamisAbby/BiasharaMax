<?php

namespace App\Domain\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Website\Http\Requests\ProductEnquiryReplyRequest;
use App\Domain\Website\Http\Resources\ProductEnquiryResource;
use App\Domain\Website\Models\ProductEnquiry;
use App\Domain\Website\Services\ProductEnquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductEnquiryController extends Controller
{
    public function __construct(
        private readonly ProductEnquiryService $enquiryService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProductEnquiry::class);

        $enquiries = ProductEnquiry::query()
            ->where('business_id', $request->user()->business_id)
            ->with('product:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Website/Enquiries', [
            'enquiries' => ProductEnquiryResource::collection($enquiries),
            'filters' => $request->only(['status']),
        ]);
    }

    public function reply(ProductEnquiryReplyRequest $request, ProductEnquiry $enquiry): RedirectResponse
    {
        $this->enquiryService->reply($enquiry, $request->validated('reply'));

        return back()->with('status', 'enquiry-replied');
    }

    public function updateStatus(Request $request, ProductEnquiry $enquiry): RedirectResponse
    {
        $this->authorize('manage', $enquiry);
        $request->validate(['status' => ['required', 'string', 'in:new,responded,closed']]);

        $this->enquiryService->updateStatus($enquiry, $request->string('status')->value());

        return back()->with('status', 'enquiry-status-updated');
    }
}
