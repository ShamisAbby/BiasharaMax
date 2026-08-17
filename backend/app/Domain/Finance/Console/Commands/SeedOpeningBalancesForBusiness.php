<?php

namespace App\Domain\Finance\Console\Commands;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\Income;
use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\JournalPostingService;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\SalePayment;
use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Posts a single "Opening Balance" journal entry per business, bootstrapping
 * the General Ledger for businesses that existed before Phase 1 shipped.
 * Computes asset/liability balances from existing source tables and posts a
 * balanced entry against the Opening Balance Equity account.
 *
 * Idempotent: skips any business that already has a posted GL entry
 * (source-table postings from AutoPostingService count, since ChartOfAccounts
 * is seeded at registration for new businesses going forward).
 *
 * Run AFTER `finance:seed-chart-of-accounts` and `finance:backfill-po-balances`.
 */
class SeedOpeningBalancesForBusiness extends Command
{
    protected $signature = 'finance:seed-opening-balances
                            {business? : A specific business ID; omit to process every business}
                            {--dry-run : Print computed balances without posting}';

    protected $description = 'Post an Opening Balance journal entry for each business that has a Chart of Accounts but no existing GL entries.';

    public function handle(ChartOfAccountsService $accounts, JournalPostingService $journal): int
    {
        $businessId = $this->argument('business');
        $dryRun = $this->option('dry-run');

        $businesses = $businessId
            ? Business::query()->where('id', $businessId)->get()
            : Business::query()->get();

        if ($businesses->isEmpty()) {
            $this->error('No matching business found.');

            return self::FAILURE;
        }

        $skipped = 0;
        $posted = 0;

        $businesses->each(function (Business $business) use ($accounts, $journal, $dryRun, &$skipped, &$posted) {
            $id = $business->getKey();

            // Skip businesses without a seeded CoA — they have no GL accounts to post to.
            if (! Account::query()->where('business_id', $id)->where('is_system_default', true)->exists()) {
                $this->warn("  {$business->name}: no Chart of Accounts — run finance:seed-chart-of-accounts first. Skipping.");
                $skipped++;

                return;
            }

            // Idempotent: skip if any GL entry already exists for this business.
            if (JournalEntry::query()->where('business_id', $id)->exists()) {
                $this->line("  {$business->name}: already has GL entries. Skipping.");
                $skipped++;

                return;
            }

            $balances = $this->computeBalances($id);

            $this->line("  {$business->name}:");
            $this->line("    Cash:      {$balances['cash']}");
            $this->line("    Bank:      {$balances['bank']}");
            $this->line("    AR:        {$balances['ar']}");
            $this->line("    Inventory: {$balances['inventory']}");
            $this->line("    AP:        {$balances['ap']}");
            $this->line("    OBE:       {$balances['obe']}");

            if ($dryRun) {
                $posted++;

                return;
            }

            DB::transaction(function () use ($id, $accounts, $journal, $balances) {
                $lines = [];

                if (bccomp($balances['cash'], '0', 2) > 0) {
                    $cash = $accounts->resolveSystemAccount($id, ChartOfAccountsService::KEY_CASH);
                    $lines[] = ['account_id' => $cash->id, 'debit' => $balances['cash'], 'description' => 'Opening cash balance'];
                }

                if (bccomp($balances['bank'], '0', 2) > 0) {
                    $bank = $accounts->resolveSystemAccount($id, ChartOfAccountsService::KEY_BANK);
                    $lines[] = ['account_id' => $bank->id, 'debit' => $balances['bank'], 'description' => 'Opening bank balance'];
                }

                if (bccomp($balances['ar'], '0', 2) > 0) {
                    $ar = $accounts->resolveSystemAccount($id, ChartOfAccountsService::KEY_ACCOUNTS_RECEIVABLE);
                    $lines[] = ['account_id' => $ar->id, 'debit' => $balances['ar'], 'description' => 'Opening accounts receivable'];
                }

                if (bccomp($balances['inventory'], '0', 2) > 0) {
                    $inv = $accounts->resolveSystemAccount($id, ChartOfAccountsService::KEY_INVENTORY);
                    $lines[] = ['account_id' => $inv->id, 'debit' => $balances['inventory'], 'description' => 'Opening inventory value'];
                }

                if (bccomp($balances['ap'], '0', 2) > 0) {
                    $ap = $accounts->resolveSystemAccount($id, ChartOfAccountsService::KEY_ACCOUNTS_PAYABLE);
                    $lines[] = ['account_id' => $ap->id, 'credit' => $balances['ap'], 'description' => 'Opening accounts payable'];
                }

                $obeAmt = $balances['obe'];
                $obeIsNegative = bccomp($obeAmt, '0', 2) < 0;
                $obeAbsAmt = $obeIsNegative ? bcmul($obeAmt, '-1', 2) : $obeAmt;

                if (bccomp($obeAbsAmt, '0', 2) > 0) {
                    $obe = $accounts->resolveSystemAccount($id, ChartOfAccountsService::KEY_OPENING_BALANCE_EQUITY);
                    $lines[] = $obeIsNegative
                        ? ['account_id' => $obe->id, 'debit' => $obeAbsAmt, 'description' => 'Opening balance equity (deficit)']
                        : ['account_id' => $obe->id, 'credit' => $obeAmt, 'description' => 'Opening balance equity'];
                }

                if (count($lines) < 2) {
                    return;
                }

                $journal->postImmediately($id, [
                    'entry_date' => now()->toDateString(),
                    'type' => JournalEntry::TYPE_MANUAL,
                    'description' => 'Opening balance — GL inception',
                ], $lines);
            });

            $posted++;
            $this->info("  {$business->name}: opening balance posted.");
        });

        $this->info("Done. Posted: {$posted}, Skipped: {$skipped}.".($dryRun ? ' [DRY RUN]' : ''));

        return self::SUCCESS;
    }

    /**
     * @return array{cash: string, bank: string, ar: string, inventory: string, ap: string, obe: string}
     */
    private function computeBalances(string $businessId): array
    {
        $cash = $this->liquidBalance($businessId, 'cash');
        $bank = $this->liquidBalance($businessId, 'bank_transfer');
        $ar = number_format((float) Customer::query()->where('business_id', $businessId)->sum('current_balance'), 2, '.', '');
        $inventory = $this->inventoryValue($businessId);
        $ap = number_format((float) Supplier::query()->where('business_id', $businessId)->sum('current_balance'), 2, '.', '');

        // Opening Balance Equity plugs the gap: total_assets - total_liabilities.
        $totalDebits = bcadd(bcadd(bcadd($cash, $bank, 2), $ar, 2), $inventory, 2);
        $obe = bcsub($totalDebits, $ap, 2); // positive → credit; negative → debit (deficit)

        return compact('cash', 'bank', 'ar', 'inventory', 'ap', 'obe');
    }

    /**
     * Cash or bank running balance: inflows (sale payments + other income)
     * minus outflows (paid expenses + approved refunds) for a given payment method.
     * Replicates FinancialReportService::balanceFor() logic without coupling to it.
     */
    private function liquidBalance(string $businessId, string $paymentMethod): string
    {
        $salesIn = (string) SalePayment::query()
            ->where('business_id', $businessId)
            ->where('payment_method', $paymentMethod)
            ->sum('amount');

        $otherIn = (string) Income::query()
            ->where('business_id', $businessId)
            ->where('payment_method', $paymentMethod)
            ->sum('amount');

        $expensesOut = (string) Expense::query()
            ->where('business_id', $businessId)
            ->where('payment_method', $paymentMethod)
            ->where('status', Expense::STATUS_PAID)
            ->sum('amount');

        $refundsOut = (string) SaleReturn::query()
            ->where('business_id', $businessId)
            ->where('refund_method', $paymentMethod)
            ->where('status', SaleReturn::STATUS_APPROVED)
            ->sum('refund_amount');

        $balance = bcsub(bcadd($salesIn, $otherIn, 2), bcadd($expensesOut, $refundsOut, 2), 2);

        // Floor at zero: a negative "cash" figure from bad data should not post a
        // credit to the Cash account — it just means we have nothing to report for
        // that payment method.
        return bccomp($balance, '0', 2) > 0 ? $balance : '0.00';
    }

    /**
     * Current inventory value at cost: sum(quantity × cost_price) across all
     * inventory rows for this business.
     */
    private function inventoryValue(string $businessId): string
    {
        $value = (string) (Inventory::query()
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->where('inventories.business_id', $businessId)
            ->selectRaw('COALESCE(SUM(inventories.quantity * products.cost_price), 0) as total')
            ->value('total') ?? '0');

        return number_format((float) $value, 2, '.', '');
    }
}
