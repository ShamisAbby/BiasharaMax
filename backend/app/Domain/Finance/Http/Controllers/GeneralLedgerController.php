<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Http\Resources\AccountResource;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Services\GeneralLedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralLedgerController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $businessId = $request->user()->business_id;

        // Balances for the whole chart in a single aggregate query. This
        // used to call accountBalance() per account inside the map — one
        // round trip per row, on a page that lists every active account.
        $balances = $this->ledger->accountBalances($businessId);

        $accounts = Account::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => [
                'account' => new AccountResource($account),
                'balance' => $this->ledger->signBalance($account, $balances[$account->id] ?? null),
            ]);

        return Inertia::render('Finance/Ledger/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function show(Request $request, Account $account): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        abort_unless($account->business_id === $request->user()->business_id, 403);

        $from = $request->string('from')->trim()->toString() ?: null;
        $to = $request->string('to')->trim()->toString() ?: null;

        $openingBalance = $from
            ? $this->ledger->accountBalance($account, date('Y-m-d', strtotime($from.' -1 day')))
            : '0.00';

        $paginated = $this->ledger->accountLedger($account, $from, $to);

        $lines = $paginated->through(fn ($line) => [
            'id' => $line->id,
            'debit' => (string) $line->debit,
            'credit' => (string) $line->credit,
            'description' => $line->description,
            'running_balance' => $line->getAttribute('running_balance'),
            'journal_entry' => [
                'id' => $line->journalEntry->id,
                'entry_number' => $line->journalEntry->entry_number,
                'entry_date' => $line->journalEntry->entry_date?->toDateString(),
                'description' => $line->journalEntry->description,
                'source_type' => $line->journalEntry->source_type,
                'source_id' => $line->journalEntry->source_id,
            ],
        ]);

        return Inertia::render('Finance/Ledger/Show', [
            'account' => new AccountResource($account),
            'ledger' => $lines,
            'opening_balance' => $openingBalance,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }
}
