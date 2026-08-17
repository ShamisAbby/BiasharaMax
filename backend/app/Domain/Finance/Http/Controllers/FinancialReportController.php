<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Http\Resources\AccountResource;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Services\FinancialStatementService;
use App\Domain\Finance\Services\GeneralLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
        private readonly FinancialStatementService $statements,
    ) {}

    public function trialBalance(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $asOfDate = $request->string('as_of')->trim()->toString() ?: now()->toDateString();
        $data = $this->ledger->trialBalance($request->user()->business_id, $asOfDate);

        return Inertia::render('Finance/Reports/TrialBalance', [
            'report' => [
                'as_of_date' => $asOfDate,
                'lines' => $data['lines']->map(fn (array $row) => [
                    'account' => new AccountResource($row['account']),
                    'debit' => $row['debit'],
                    'credit' => $row['credit'],
                ]),
                'total_debit' => $data['total_debit'],
                'total_credit' => $data['total_credit'],
                'is_balanced' => bccomp($data['total_debit'], $data['total_credit'], 2) === 0,
            ],
        ]);
    }

    public function profitAndLoss(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $from = $request->string('from')->trim()->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->trim()->toString() ?: now()->toDateString();
        $businessId = $request->user()->business_id;
        $data = $this->statements->profitAndLoss($businessId, $from, $to);

        return Inertia::render('Finance/Reports/ProfitAndLoss', [
            'report' => [
                'from_date' => $data['from_date'],
                'to_date' => $data['to_date'],
                'revenue_accounts' => array_map(fn (array $row) => [
                    'account' => new AccountResource($row['account']),
                    'amount' => $row['amount'],
                ], $data['revenue_accounts']),
                'total_revenue' => $data['total_revenue'],
                'expense_accounts' => array_map(fn (array $row) => [
                    'account' => new AccountResource($row['account']),
                    'amount' => $row['amount'],
                ], $data['expense_accounts']),
                'total_expenses' => $data['total_expenses'],
                'net_profit' => $data['net_profit'],
            ],
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $asOfDate = $request->string('as_of')->trim()->toString() ?: now()->toDateString();
        $data = $this->statements->balanceSheet($request->user()->business_id, $asOfDate);

        $mapAccount = fn (array $row) => [
            'account' => new AccountResource($row['account']),
            'balance' => $row['balance'],
        ];

        return Inertia::render('Finance/Reports/BalanceSheet', [
            'report' => [
                'as_of_date' => $data['as_of_date'],
                'assets' => array_map($mapAccount, $data['assets']),
                'total_assets' => $data['total_assets'],
                'liabilities' => array_map($mapAccount, $data['liabilities']),
                'total_liabilities' => $data['total_liabilities'],
                'equity' => $data['equity'],
                'total_equity' => $data['total_equity'],
                'is_balanced' => $data['is_balanced'],
            ],
        ]);
    }

    public function cashFlow(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $from = $request->string('from')->trim()->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->trim()->toString() ?: now()->toDateString();
        $data = $this->statements->cashFlowStatement($request->user()->business_id, $from, $to);

        return Inertia::render('Finance/Reports/CashFlow', [
            'report' => $data,
        ]);
    }

    // ── PDF downloads ─────────────────────────────────────────────────────────

    public function trialBalancePdf(Request $request): HttpResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        $asOfDate = $request->string('as_of')->trim()->toString() ?: now()->toDateString();
        $businessId = $request->user()->business_id;
        $data = $this->ledger->trialBalance($businessId, $asOfDate);
        $business = $request->user()->business;

        $report = [
            'as_of_date' => $asOfDate,
            'lines' => $data['lines']->map(fn (array $row) => [
                'account' => (new AccountResource($row['account']))->toArray($request),
                'debit'   => $row['debit'],
                'credit'  => $row['credit'],
            ])->values()->toArray(),
            'total_debit'  => $data['total_debit'],
            'total_credit' => $data['total_credit'],
            'is_balanced'  => bccomp($data['total_debit'], $data['total_credit'], 2) === 0,
        ];

        return Pdf::loadView('reports.financial-statement', [
            'type'         => 'trial_balance',
            'businessName' => $business->name,
            'reportTitle'  => 'Trial Balance',
            'period'       => 'As of ' . Carbon::parse($asOfDate)->format('F j, Y'),
            'generatedAt'  => now()->format('M j, Y g:i A'),
            'report'       => $report,
        ])->setPaper('a4', 'portrait')->download("trial-balance-{$asOfDate}.pdf");
    }

    public function profitAndLossPdf(Request $request): HttpResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        $from = $request->string('from')->trim()->toString() ?: now()->startOfMonth()->toDateString();
        $to   = $request->string('to')->trim()->toString()   ?: now()->toDateString();
        $businessId = $request->user()->business_id;
        $data = $this->statements->profitAndLoss($businessId, $from, $to);
        $business = $request->user()->business;

        $report = [
            'from_date'        => $data['from_date'],
            'to_date'          => $data['to_date'],
            'revenue_accounts' => array_map(fn ($row) => [
                'account' => (new AccountResource($row['account']))->toArray($request),
                'amount'  => $row['amount'],
            ], $data['revenue_accounts']),
            'total_revenue'    => $data['total_revenue'],
            'expense_accounts' => array_map(fn ($row) => [
                'account' => (new AccountResource($row['account']))->toArray($request),
                'amount'  => $row['amount'],
            ], $data['expense_accounts']),
            'total_expenses'   => $data['total_expenses'],
            'net_profit'       => $data['net_profit'],
        ];

        $period = Carbon::parse($from)->format('M j, Y') . ' – ' . Carbon::parse($to)->format('M j, Y');

        return Pdf::loadView('reports.financial-statement', [
            'type'         => 'profit_and_loss',
            'businessName' => $business->name,
            'reportTitle'  => 'Profit & Loss Statement',
            'period'       => $period,
            'generatedAt'  => now()->format('M j, Y g:i A'),
            'report'       => $report,
        ])->setPaper('a4', 'portrait')->download("profit-and-loss-{$from}-to-{$to}.pdf");
    }

    public function balanceSheetPdf(Request $request): HttpResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        $asOfDate = $request->string('as_of')->trim()->toString() ?: now()->toDateString();
        $businessId = $request->user()->business_id;
        $data = $this->statements->balanceSheet($businessId, $asOfDate);
        $business = $request->user()->business;

        $mapAccount = fn ($row) => [
            'account' => (new AccountResource($row['account']))->toArray($request),
            'balance' => $row['balance'],
        ];

        $report = [
            'as_of_date'       => $data['as_of_date'],
            'assets'           => array_map($mapAccount, $data['assets']),
            'total_assets'     => $data['total_assets'],
            'liabilities'      => array_map($mapAccount, $data['liabilities']),
            'total_liabilities'=> $data['total_liabilities'],
            'equity'           => $data['equity'],
            'total_equity'     => $data['total_equity'],
            'is_balanced'      => $data['is_balanced'],
        ];

        return Pdf::loadView('reports.financial-statement', [
            'type'         => 'balance_sheet',
            'businessName' => $business->name,
            'reportTitle'  => 'Balance Sheet',
            'period'       => 'As of ' . Carbon::parse($asOfDate)->format('F j, Y'),
            'generatedAt'  => now()->format('M j, Y g:i A'),
            'report'       => $report,
        ])->setPaper('a4', 'portrait')->download("balance-sheet-{$asOfDate}.pdf");
    }

    public function cashFlowPdf(Request $request): HttpResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        $from = $request->string('from')->trim()->toString() ?: now()->startOfMonth()->toDateString();
        $to   = $request->string('to')->trim()->toString()   ?: now()->toDateString();
        $data = $this->statements->cashFlowStatement($request->user()->business_id, $from, $to);
        $business = $request->user()->business;

        $period = Carbon::parse($from)->format('M j, Y') . ' – ' . Carbon::parse($to)->format('M j, Y');

        return Pdf::loadView('reports.financial-statement', [
            'type'         => 'cash_flow',
            'businessName' => $business->name,
            'reportTitle'  => 'Cash Flow Statement',
            'period'       => $period,
            'generatedAt'  => now()->format('M j, Y g:i A'),
            'report'       => $data,
        ])->setPaper('a4', 'portrait')->download("cash-flow-{$from}-to-{$to}.pdf");
    }
}
