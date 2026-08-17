<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Http\Requests\BankAccountStoreRequest;
use App\Domain\Finance\Http\Requests\BankTransferRequest;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Services\BankAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BankAccount::class);

        $bankAccounts = $this->service->listForBusiness($request->user()->business_id);

        $glAccounts = Account::query()
            ->where('business_id', $request->user()->business_id)
            ->where('is_active', true)
            ->whereIn('type', ['asset'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Finance/Bank/Index', [
            'bankAccounts' => $bankAccounts->map(fn (BankAccount $ba) => [
                'id' => $ba->id,
                'bank_name' => $ba->bank_name,
                'account_number' => $ba->account_number,
                'account_holder_name' => $ba->account_holder_name,
                'is_active' => $ba->is_active,
                'current_balance' => (float) $ba->currentBalance(),
                'opening_balance' => (float) $ba->opening_balance,
                'currency' => $ba->currency ? ['code' => $ba->currency->code, 'symbol' => $ba->currency->symbol] : null,
                'account' => $ba->account ? ['id' => $ba->account->id, 'code' => $ba->account->code, 'name' => $ba->account->name] : null,
                'last_reconciliation' => $ba->lastReconciliation() ? [
                    'period_end' => $ba->lastReconciliation()->period_end?->toDateString(),
                    'status' => $ba->lastReconciliation()->status,
                ] : null,
            ]),
            'glAccounts' => $glAccounts,
        ]);
    }

    public function store(BankAccountStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $this->service->create($request->user()->business_id, $data);

        return back()->with('status', 'bank-account-created');
    }

    public function show(Request $request, BankAccount $bankAccount): Response
    {
        $this->authorize('view', $bankAccount);

        $transactions = $bankAccount->bankTransactions()
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate(50);

        $reconciliations = $bankAccount->reconciliations()
            ->orderByDesc('period_end')
            ->get();

        return Inertia::render('Finance/Bank/Show', [
            'bankAccount' => [
                'id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'account_number' => $bankAccount->account_number,
                'account_holder_name' => $bankAccount->account_holder_name,
                'opening_balance' => (float) $bankAccount->opening_balance,
                'current_balance' => (float) $bankAccount->currentBalance(),
                'is_active' => $bankAccount->is_active,
                'account' => $bankAccount->account ? ['id' => $bankAccount->account->id, 'code' => $bankAccount->account->code, 'name' => $bankAccount->account->name] : null,
            ],
            'transactions' => $transactions->through(fn ($t) => [
                'id' => $t->id,
                'transaction_date' => $t->transaction_date->toDateString(),
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'reference' => $t->reference,
                'description' => $t->description,
                'reconciliation_status' => $t->reconciliation_status,
                'reconciled_at' => $t->reconciled_at?->toDateTimeString(),
                'journal_entry_id' => $t->journal_entry_id,
            ]),
            'reconciliations' => $reconciliations->map(fn ($r) => [
                'id' => $r->id,
                'period_start' => $r->period_start->toDateString(),
                'period_end' => $r->period_end->toDateString(),
                'statement_balance' => (float) $r->statement_balance,
                'book_balance' => (float) $r->book_balance,
                'difference' => (float) $r->difference,
                'status' => $r->status,
                'completed_at' => $r->completed_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        $data = $request->validate([
            'bank_name' => ['sometimes', 'string', 'max:100'],
            'account_number' => ['sometimes', 'string', 'max:50'],
            'account_holder_name' => ['sometimes', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['updated_by'] = $request->user()->id;

        $this->service->update($bankAccount, $data);

        return back()->with('status', 'bank-account-updated');
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('delete', $bankAccount);
        $this->service->delete($bankAccount);

        return redirect()->route('finance.bank.index')->with('status', 'bank-account-deleted');
    }

    public function transfer(BankTransferRequest $request): RedirectResponse
    {
        $this->authorize('create', BankAccount::class);

        $data = $request->validated();
        $businessId = $request->user()->business_id;

        $from = $this->service->findInBusiness($data['from_bank_account_id'], $businessId);
        $to = $this->service->findInBusiness($data['to_bank_account_id'], $businessId);

        $this->service->transfer(
            $from,
            $to,
            number_format((float) $data['amount'], 2, '.', ''),
            $data['date'],
            $request->user()->id,
            $data['reference'] ?? null,
            $data['description'] ?? null,
        );

        return back()->with('status', 'transfer-completed');
    }
}
