<?php

namespace App\Domain\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Http\Requests\CustomerTagStoreRequest;
use App\Domain\CRM\Http\Requests\CustomerTagUpdateRequest;
use App\Domain\CRM\Http\Resources\CustomerTagResource;
use App\Domain\CRM\Models\CustomerTag;
use App\Domain\CRM\Services\CustomerTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerTagController extends Controller
{
    public function __construct(
        private readonly CustomerTagService $tagService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerTag::class);

        $tags = CustomerTag::query()
            ->withCount('customers')
            ->orderBy('name')
            ->get();

        return Inertia::render('Crm/Tags/Index', [
            'tags' => CustomerTagResource::collection($tags),
        ]);
    }

    public function store(CustomerTagStoreRequest $request): RedirectResponse
    {
        $this->tagService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'customer-tag-created');
    }

    public function update(CustomerTagUpdateRequest $request, CustomerTag $tag): RedirectResponse
    {
        $this->tagService->update($tag, $request->validated());

        return back()->with('status', 'customer-tag-updated');
    }

    public function destroy(CustomerTag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);
        $this->tagService->delete($tag);

        return back()->with('status', 'customer-tag-deleted');
    }
}
