<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Exceptions\BankReconciliationException;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\BankReconciliation;
use App\Domain\Finance\Models\BankTransaction;
use App\Domain\Finance\Services\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $service,
    ) {}

    public function index(Request $request, BankAccount $bankAccount): Response
    {
        $this->authorize('reconcile', $bankAccount);

        $reconciliations = $bankAccount->reconciliations()
            ->orderByDesc('period_end')
            ->get();

        return Inertia::render('Finance/Bank/Reconciliation', [
            'bankAccount' => [
                'id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'account_number' => $bankAccount->account_number,
                'current_balance' => (float) $bankAccount->currentBalance(),
            ],
            'reconciliations' => $reconciliations->map(fn (BankReconciliation $r) => [
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

    public function start(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('reconcile', $bankAccount);

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'statement_balance' => ['required', 'numeric'],
        ]);

        $this->service->startReconciliation(
            $bankAccount,
            $data['period_start'],
            $data['period_end'],
            number_format((float) $data['statement_balance'], 2, '.', ''),
            $request->user()->id,
        );

        return back()->with('status', 'reconciliation-started');
    }

    public function markItem(Request $request, BankAccount $bankAccount, BankReconciliation $reconciliation, BankTransaction $transaction): RedirectResponse
    {
        $this->authorize('reconcile', $bankAccount);

        $this->service->markReconciled($transaction, $reconciliation);

        return back()->with('status', 'item-toggled');
    }

    public function complete(Request $request, BankAccount $bankAccount, BankReconciliation $reconciliation): RedirectResponse
    {
        $this->authorize('reconcile', $bankAccount);

        try {
            $this->service->completeReconciliation($reconciliation);
        } catch (BankReconciliationException $e) {
            throw ValidationException::withMessages(['reconciliation' => $e->getMessage()]);
        }

        return back()->with('status', 'reconciliation-completed');
    }
}
