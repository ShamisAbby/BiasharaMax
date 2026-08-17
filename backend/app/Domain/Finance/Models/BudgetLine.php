<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    protected $fillable = [
        'budget_id',
        'account_id',
        'period_start',
        'period_end',
        'budgeted_amount',
        'budgeted_amount_minor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'budgeted_amount' => 'decimal:2',
            'budgeted_amount_minor' => 'integer',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['budgeted_amount' => 'budgeted_amount_minor'];
    }

    /**
     * No `business` relation of its own — resolve currency via the
     * budget it belongs to instead.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->budget?->business?->currency ?? 'TZS';
    }
}
