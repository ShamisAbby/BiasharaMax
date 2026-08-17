<?php

namespace App\Domain\Finance\Models;

use App\Domain\Authentication\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodClosingEntry extends Model
{
    use HasUuids;

    public const TYPE_INCOME_SUMMARY = 'income_summary';

    public const TYPE_RETAINED_EARNINGS = 'retained_earnings';

    protected $fillable = [
        'business_id',
        'financial_period_id',
        'closing_journal_entry_id',
        'closing_type',
        'posted_by',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'closing_journal_entry_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
