<?php

namespace App\Domain\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'business_id',
        'payroll_period_id',
        'employee_profile_id',
        'basic_salary',
        'basic_salary_minor',
        'total_allowances',
        'total_allowances_minor',
        'gross_salary',
        'gross_salary_minor',
        'income_tax',
        'income_tax_minor',
        'social_security',
        'social_security_minor',
        'other_deductions',
        'other_deductions_minor',
        'total_deductions',
        'total_deductions_minor',
        'net_salary',
        'net_salary_minor',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'basic_salary_minor' => 'integer',
            'total_allowances' => 'decimal:2',
            'total_allowances_minor' => 'integer',
            'gross_salary' => 'decimal:2',
            'gross_salary_minor' => 'integer',
            'income_tax' => 'decimal:2',
            'income_tax_minor' => 'integer',
            'social_security' => 'decimal:2',
            'social_security_minor' => 'integer',
            'other_deductions' => 'decimal:2',
            'other_deductions_minor' => 'integer',
            'total_deductions' => 'decimal:2',
            'total_deductions_minor' => 'integer',
            'net_salary' => 'decimal:2',
            'net_salary_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayslipDeduction::class);
    }
}
