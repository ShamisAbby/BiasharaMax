<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Exceptions\JournalEntryException;
use App\Domain\Finance\Exceptions\UnbalancedJournalEntryException;
use App\Domain\Finance\Http\Requests\JournalEntryReverseRequest;
use App\Domain\Finance\Http\Requests\JournalEntryStoreRequest;
use App\Domain\Finance\Http\Requests\JournalEntryVoidRequest;
use App\Domain\Finance\Http\Resources\JournalEntryResource;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Services\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalPostingService $postingService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $entries = JournalEntry::query()
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(function ($query) use ($search) {
                    $query->where('entry_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('entry_date')
            ->orderByDesc('entry_number')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Finance/Journal/Index', [
            'entries' => JournalEntryResource::collection($entries),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', JournalEntry::class);

        return Inertia::render('Finance/Journal/Create', [
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type', 'normal_balance']),
        ]);
    }

    public function store(JournalEntryStoreRequest $request): RedirectResponse
    {
        try {
            $entry = $this->postingService->createDraft(
                $request->user()->business_id,
                $request->only(['entry_date', 'description', 'memo']),
                $request->input('lines'),
                $request->user()->id,
            );
        } catch (UnbalancedJournalEntryException|JournalEntryException $e) {
            throw ValidationException::withMessages(['lines' => $e->getMessage()]);
        }

        return redirect()->route('finance.journal.show', $entry)->with('status', 'journal-entry-created');
    }

    public function show(JournalEntry $entry): Response
    {
        $this->authorize('view', $entry);

        $entry->load(['lines.account', 'postedBy', 'voidedBy']);

        return Inertia::render('Finance/Journal/Show', [
            'entry' => new JournalEntryResource($entry),
        ]);
    }

    public function post(Request $request, JournalEntry $entry): RedirectResponse
    {
        $this->authorize('post', $entry);

        try {
            $this->postingService->post($entry, $request->user()->id);
        } catch (JournalEntryException|UnbalancedJournalEntryException $e) {
            throw ValidationException::withMessages(['entry' => $e->getMessage()]);
        }

        return back()->with('status', 'journal-entry-posted');
    }

    public function reverse(JournalEntryReverseRequest $request, JournalEntry $entry): RedirectResponse
    {
        try {
            $this->postingService->reverse($entry, $request->user()->id, $request->validated('reason'));
        } catch (JournalEntryException $e) {
            throw ValidationException::withMessages(['entry' => $e->getMessage()]);
        }

        return back()->with('status', 'journal-entry-reversed');
    }

    public function void(JournalEntryVoidRequest $request, JournalEntry $entry): RedirectResponse
    {
        try {
            $this->postingService->void($entry, $request->user()->id, $request->validated('reason'));
        } catch (JournalEntryException $e) {
            throw ValidationException::withMessages(['entry' => $e->getMessage()]);
        }

        return back()->with('status', 'journal-entry-voided');
    }
}
