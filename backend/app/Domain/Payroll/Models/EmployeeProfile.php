<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeProfile extends Model
{
    use BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_TERMINATED = 'terminated';

    public const TYPE_FULL_TIME = 'full_time';

    public const TYPE_PART_TIME = 'part_time';

    public const TYPE_CONTRACT = 'contract';

    protected $fillable = [
        'business_id',
        'user_id',
        'employee_number',
        'employment_date',
        'employment_type',
        'department',
        'position',
        'base_salary',
        'base_salary_minor',
        'salary_cycle',
        'tax_identification_number',
        'bank_account_number',
        'bank_name',
        'status',
        'termination_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'employment_date' => 'date',
            'termination_date' => 'date',
            'base_salary' => 'decimal:2',
            'base_salary_minor' => 'integer',
        ];
    }

    /**
     * `base_salary_minor` is the source of truth going forward (see
     * docs/ADR/0002-money-format-migration.md); `base_salary` (decimal) is
     * still dual-written by PayrollService for anything not yet cut over.
     * Falls back to the decimal column if `_minor` hasn't been backfilled
     * for this row yet (shouldn't happen after the migration, but a bare
     * fallback here is cheap insurance against a row that slipped through).
     *
     * Accepts an explicit $currency so a caller iterating many employees in
     * the same business (PayrollService::generatePayslips()) can resolve
     * the business's currency once and pass it through, instead of every
     * row lazy-loading its own `business` relation (N+1). Falls back to the
     * `business` relation, then 'TZS', only when the caller doesn't know it
     * already.
     */
    public function baseSalaryMoney(?string $currency = null): Money
    {
        $currency ??= $this->business?->currency ?? 'TZS';

        if ($this->base_salary_minor !== null) {
            return Money::fromMinorUnits($this->base_salary_minor, $currency);
        }

        return Money::fromDecimal($this->base_salary, $currency);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(SalaryAllowance::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Kept returning a decimal string (not Money) since existing callers
     * (EmployeeProfileController) already treat this as a display string —
     * changing the return type would ripple into the controller/frontend,
     * out of scope for this pass. Internally now computed via Money so the
     * arithmetic itself doesn't reintroduce float/decimal-string drift.
     */
    public function grossSalary(): string
    {
        $currency = $this->business?->currency ?? 'TZS';
        $allowancesTotalMinor = (int) $this->allowances()->where('is_active', true)->sum('amount_minor');

        return $this->baseSalaryMoney($currency)
            ->add(Money::fromMinorUnits($allowancesTotalMinor, $currency))
            ->toDecimalString();
    }
}
