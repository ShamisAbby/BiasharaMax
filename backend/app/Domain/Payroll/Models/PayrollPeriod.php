<?php

namespace App\Domain\Payroll\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use BelongsToTenant, HasUserstamps, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'business_id',
        'period_name',
        'period_start',
        'period_end',
        'salary_cycle',
        'status',
        'total_gross',
        'total_gross_minor',
        'total_deductions',
        'total_deductions_minor',
        'total_net',
        'total_net_minor',
        'approved_by',
        'approved_at',
        'paid_at',
        'journal_entry_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_gross' => 'decimal:2',
            'total_gross_minor' => 'integer',
            'total_deductions' => 'decimal:2',
            'total_deductions_minor' => 'integer',
            'total_net' => 'decimal:2',
            'total_net_minor' => 'integer',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
