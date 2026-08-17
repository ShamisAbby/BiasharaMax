<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationSchedule extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    public const STATUS_PENDING = 'pending';

    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'business_id',
        'fixed_asset_id',
        'period_date',
        'depreciation_amount',
        'depreciation_amount_minor',
        'accumulated_depreciation',
        'accumulated_depreciation_minor',
        'book_value',
        'book_value_minor',
        'journal_entry_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'depreciation_amount' => 'decimal:2',
            'depreciation_amount_minor' => 'integer',
            'accumulated_depreciation' => 'decimal:2',
            'accumulated_depreciation_minor' => 'integer',
            'book_value' => 'decimal:2',
            'book_value_minor' => 'integer',
        ];
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
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
            'depreciation_amount' => 'depreciation_amount_minor',
            'accumulated_depreciation' => 'accumulated_depreciation_minor',
            'book_value' => 'book_value_minor',
        ];
    }

    /**
     * No `business` relation of its own — resolve currency via the fixed
     * asset it belongs to instead.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->fixedAsset?->business?->currency ?? 'TZS';
    }
}
