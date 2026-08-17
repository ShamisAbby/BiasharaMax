<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of the money-format migration (docs/ADR/0002-money-format-migration.md)
 * for the Payroll bounded context — first in the rollout order since it has
 * the fewest cross-context dependencies.
 *
 * Additive + backfill only: adds integer minor-unit columns alongside the
 * existing decimal columns and copies the equivalent value across. Nothing
 * reads the new columns yet — that's the next step, done per-context in its
 * own commit, per the ADR's dual-write rollout. The old decimal columns stay
 * authoritative until every context has cut over and been verified.
 *
 * Signed (not unsigned) bigInteger: safer default while it's unconfirmed
 * whether any of these can legitimately go negative (e.g. a correction).
 */
return new class extends Migration
{
    private array $tables = [
        'employee_profiles' => ['base_salary'],
        'salary_allowances' => ['amount'],
        'payroll_periods' => ['total_gross', 'total_deductions', 'total_net'],
        'payslips' => [
            'basic_salary', 'total_allowances', 'gross_salary', 'income_tax',
            'social_security', 'other_deductions', 'total_deductions', 'net_salary',
        ],
        'payslip_deductions' => ['amount'],
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
