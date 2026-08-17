<?php

namespace App\Domain\Finance\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxTransaction extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    public const UPDATED_AT = null;

    public const TYPE_OUTPUT = 'output';

    public const TYPE_INPUT = 'input';

    protected $fillable = [
        'business_id',
        'tax_config_id',
        'journal_entry_id',
        'transaction_type',
        'taxable_amount',
        'taxable_amount_minor',
        'tax_amount',
        'tax_amount_minor',
        'transaction_date',
        'period_start',
        'period_end',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'taxable_amount' => 'decimal:2',
            'taxable_amount_minor' => 'integer',
            'tax_amount' => 'decimal:2',
            'tax_amount_minor' => 'integer',
        ];
    }

    public function taxConfig(): BelongsTo
    {
        return $this->belongsTo(TaxConfiguration::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'taxable_amount' => 'taxable_amount_minor',
            'tax_amount' => 'tax_amount_minor',
        ];
    }

    /**
     * No `business` relation of its own — business_id is a plain column
     * here, so resolve currency with a direct lookup.
     */
    protected function moneyMinorCurrency(): string
    {
        return Business::query()->find($this->business_id)?->currency ?? 'TZS';
    }
}
