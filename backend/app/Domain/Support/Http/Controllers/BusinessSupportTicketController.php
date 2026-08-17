<?php

namespace App\Domain\Support\Http\Controllers;

use App\Domain\Support\Http\Requests\StoreBusinessSupportTicketRequest;
use App\Domain\Support\Http\Requests\BusinessSupportTicketReplyRequest;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Support\Services\SupportTicketService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The business side of support: raise a ticket with the platform team,
 * follow the thread, reply, close.
 *
 * **SupportTicket has no `BelongsToTenant` trait**, and that is correct —
 * platform admins must see every business's tickets. The consequence is
 * that nothing scopes these queries automatically, so every one of them
 * filters on `business_id` by hand.
 *
 * Route-model binding is deliberately not used for that reason. A
 * `SupportTicket $ticket` parameter would resolve any ticket on the
 * platform by id, and the only thing standing between a business and
 * another business's support thread would be a check somebody remembered
 * to write. Resolving through [ticketFor] makes the scope part of the
 * lookup instead of a guard bolted on after it.
 */
class BusinessSupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = $this->scoped($request)
            ->withCount(['messages' => fn ($q) => $q->where('is_internal_note', false)])
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SupportTicket $ticket): array => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'messages_count' => $ticket->messages_count,
                'created_at' => $ticket->created_at,
            ]);

        return Inertia::render('Support/Index', [
            // `{data, meta}`, the shape every paginated screen in this app
            // uses. Passing the paginator raw puts `links` at the top
            // level with no `meta`, which the pages do not read — so
            // pagination silently disappears and older tickets become
            // unreachable rather than the page failing loudly.
            'tickets' => [
                'data' => $tickets->items(),
                'meta' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'total' => $tickets->total(),
                    'links' => $tickets->linkCollection()->toArray(),
                ],
            ],
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
        ]);
    }

    public function store(StoreBusinessSupportTicketRequest $request, SupportTicketService $service): RedirectResponse
    {
        $ticket = $service->open([
            'business_id' => $request->user()->business_id,
            // Polymorphic, and the tenant side is always 'user'. The
            // platform side writes 'platform_user' — the two are
            // different tables, so this is what tells a reply from the
            // customer apart from a reply from support.
            'opened_by_type' => 'user',
            'opened_by_id' => $request->user()->id,
            'category' => $request->validated('category'),
            'priority' => $request->validated('priority'),
            'subject' => $request->validated('subject'),
            'description' => $request->validated('description'),
        ]);

        return redirect()
            ->route('support.show', $ticket->id)
            ->with('status', 'ticket-opened');
    }

    public function show(Request $request, string $ticket): Response
    {
        $model = $this->ticketFor($request, $ticket);

        return Inertia::render('Support/Show', [
            'ticket' => [
                'id' => $model->id,
                'ticket_number' => $model->ticket_number,
                'subject' => $model->subject,
                'description' => $model->description,
                'category' => $model->category,
                'priority' => $model->priority,
                'status' => $model->status,
                'created_at' => $model->created_at,
                'resolved_at' => $model->resolved_at,
                'closed_at' => $model->closed_at,
            ],
            'messages' => $model->messages()
                /*
                 * Internal notes are support staff talking to each other
                 * about this customer. Filtered in the query rather than
                 * hidden in the view, so a future change to the page
                 * cannot accidentally reveal them.
                 */
                ->where('is_internal_note', false)
                ->orderBy('created_at')
                ->get()
                ->map(fn (SupportTicketMessage $message): array => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'from_support' => $message->author_type === 'platform_user',
                    'created_at' => $message->created_at,
                ]),
        ]);
    }

    public function reply(BusinessSupportTicketReplyRequest $request, string $ticket, SupportTicketService $service): RedirectResponse
    {
        $model = $this->ticketFor($request, $ticket);

        abort_if(
            $model->status === SupportTicket::STATUS_CLOSED,
            403,
            'This ticket is closed. Open a new one and reference '.$model->ticket_number.'.',
        );

        $service->reply($model, 'user', $request->user()->id, $request->validated('body'));

        /*
         * A customer replying to a resolved ticket is disagreeing that it
         * is resolved. Reopening automatically is the difference between
         * that reply being seen and it sitting in a queue nobody looks at
         * because the ticket reads as done.
         */
        if ($model->status === SupportTicket::STATUS_RESOLVED) {
            $service->reopen($model);
        }

        return back()->with('status', 'reply-sent');
    }

    public function close(Request $request, string $ticket, SupportTicketService $service): RedirectResponse
    {
        $service->close($this->ticketFor($request, $ticket));

        return back()->with('status', 'ticket-closed');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<SupportTicket>
     */
    private function scoped(Request $request)
    {
        return SupportTicket::query()->where('business_id', $request->user()->business_id);
    }

    /**
     * Resolves a ticket that belongs to the caller's business, or 404s.
     *
     * 404 rather than 403 on purpose: a business should not be able to
     * discover that a ticket id exists by being told it is forbidden.
     */
    private function ticketFor(Request $request, string $ticket): SupportTicket
    {
        return $this->scoped($request)->findOrFail($ticket);
    }

    /** @var array<string, string> */
    public const CATEGORIES = [
        'technical' => 'Technical problem',
        'billing' => 'Billing or subscription',
        'account' => 'Account and access',
        'feature_request' => 'Feature request',
        'other' => 'Something else',
    ];

    /** @var array<string, string> */
    public const PRIORITIES = [
        SupportTicket::PRIORITY_LOW => 'Low — a question, no rush',
        SupportTicket::PRIORITY_MEDIUM => 'Medium — affecting my work',
        SupportTicket::PRIORITY_HIGH => 'High — I cannot trade properly',
        SupportTicket::PRIORITY_URGENT => 'Urgent — business is stopped',
    ];
}
