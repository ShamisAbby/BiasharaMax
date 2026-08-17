<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Platform\Http\Requests\SupportTicketReplyRequest;
use App\Domain\Platform\Http\Resources\SupportTicketResource;
use App\Domain\Support\Models\SupportAgent;
use App\Domain\Support\Models\SupportDepartment;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = SupportTicket::query()
            ->with(['business', 'department', 'assignedAgent.platformUser'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->trim()->value();
                $q->where(fn ($q2) => $q2->where('subject', 'like', "%{$search}%")->orWhere('ticket_number', 'like', "%{$search}%"));
            })
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Platform/Operations/Support/Index', [
            'tickets' => SupportTicketResource::collection($tickets),
            'departments' => SupportDepartment::query()->orderBy('name')->get(['id', 'name']),
            'agents' => SupportAgent::query()->with('platformUser')->get()->map(fn ($a) => ['id' => $a->id, 'name' => $a->platformUser?->name]),
            'filters' => $request->only(['status', 'priority', 'search']),
        ]);
    }

    public function show(SupportTicket $supportTicket): Response
    {
        $supportTicket->load(['business', 'department', 'assignedAgent.platformUser', 'messages']);

        return Inertia::render('Platform/Operations/Support/Show', [
            'ticket' => new SupportTicketResource($supportTicket),
        ]);
    }

    public function reply(SupportTicketReplyRequest $request, SupportTicket $supportTicket, SupportTicketService $service): RedirectResponse
    {
        $service->reply($supportTicket, 'platform_user', $request->user()->id, $request->validated('body'), $request->boolean('is_internal_note'));

        return back()->with('status', 'reply-added');
    }

    public function assign(Request $request, SupportTicket $supportTicket, SupportTicketService $service): RedirectResponse
    {
        $validated = $request->validate(['agent_id' => ['required', 'uuid', 'exists:support_agents,id']]);
        $agent = SupportAgent::query()->findOrFail($validated['agent_id']);

        $service->assign($supportTicket, $agent);

        return back()->with('status', 'ticket-assigned');
    }

    public function resolve(SupportTicket $supportTicket, SupportTicketService $service): RedirectResponse
    {
        $service->resolve($supportTicket);

        return back()->with('status', 'ticket-resolved');
    }

    public function close(SupportTicket $supportTicket, SupportTicketService $service): RedirectResponse
    {
        $service->close($supportTicket);

        return back()->with('status', 'ticket-closed');
    }

    public function reopen(SupportTicket $supportTicket, SupportTicketService $service): RedirectResponse
    {
        $service->reopen($supportTicket);

        return back()->with('status', 'ticket-reopened');
    }
}
