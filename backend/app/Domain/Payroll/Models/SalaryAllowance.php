<?php

namespace App\Domain\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAllowance extends Model
{
    use HasUuids;

    public const TYPE_HOUSING = 'housing';

    public const TYPE_TRANSPORT = 'transport';

    public const TYPE_MEDICAL = 'medical';

    public const TYPE_PERFORMANCE = 'performance';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'employee_profile_id',
        'allowance_type',
        'amount',
        'amount_minor',
        'is_taxable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }
}
