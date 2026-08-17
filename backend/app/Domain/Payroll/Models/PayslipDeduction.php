<?php

namespace App\Domain\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipDeduction extends Model
{
    use HasUuids;

    public const TYPE_INCOME_TAX = 'income_tax';

    public const TYPE_NHIF = 'nhif';

    public const TYPE_NSSF = 'nssf';

    public const TYPE_PENSION = 'pension';

    public const TYPE_LOAN_REPAYMENT = 'loan_repayment';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'payslip_id',
        'deduction_type',
        'description',
        'amount',
        'amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
