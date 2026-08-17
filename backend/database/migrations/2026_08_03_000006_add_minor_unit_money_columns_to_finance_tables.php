<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of the money-format migration (docs/ADR/0002-money-format-migration.md)
 * for Finance — last in the rollout order, since the ledger has the highest
 * blast radius (debit=credit must hold under the new columns too — verify
 * with SUM(debit_minor) = SUM(credit_minor) per business/period before
 * cutting the model over, not just after). Additive + backfill only.
 *
 * Includes Accounting's expenses/incomes (being merged into Finance per
 * docs/ADR/0001-consolidation.md) and Subscription's platform-billing
 * tables (platform billing, not tenant business data, but still money and
 * with no separate step of its own in the rollout order).
 */
return new class extends Migration
{
    private array $tables = [
        'journal_lines' => ['debit', 'credit', 'foreign_amount'],
        'bank_accounts' => ['opening_balance'],
        'bank_transactions' => ['amount'],
        'bank_reconciliations' => ['statement_balance', 'book_balance', 'difference'],
        'budget_lines' => ['budgeted_amount'],
        'tax_transactions' => ['taxable_amount', 'tax_amount'],
        'fixed_assets' => ['acquisition_cost', 'residual_value', 'disposal_proceeds'],
        'depreciation_schedules' => ['depreciation_amount', 'accumulated_depreciation', 'book_value'],
        'payment_transactions' => ['amount', 'tax_amount', 'discount_amount', 'fee_amount', 'commission_amount', 'refunded_amount'],
        'payment_gateways' => ['fee_fixed'],
        'expenses' => ['amount'],
        'incomes' => ['amount'],
        'subscription_plans' => ['price_monthly', 'price_quarterly', 'price_yearly', 'price_lifetime'],
        'subscriptions' => ['custom_price'],
        'subscription_transactions' => ['amount'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->bigInteger("{$column}_minor")->nullable()->after($column);
                }
            });

            foreach ($columns as $column) {
                DB::statement("update {$table} set {$column}_minor = round({$column} * 100) where {$column} is not null");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropColumn("{$column}_minor");
                }
            });
        }
    }
};
