<?php

namespace App\Domain\Finance\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sales\Models\Customer;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    public const UPDATED_AT = null;

    protected $attributes = [
        'debit' => 0,
        'debit_minor' => 0,
        'credit' => 0,
        'credit_minor' => 0,
        'line_number' => 1,
    ];

    protected $fillable = [
        'business_id',
        'journal_entry_id',
        'account_id',
        'debit',
        'debit_minor',
        'credit',
        'credit_minor',
        'description',
        'customer_id',
        'supplier_id',
        'line_number',
        'currency_id',
        'exchange_rate',
        'foreign_amount',
        'foreign_amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'debit_minor' => 'integer',
            'credit' => 'decimal:2',
            'credit_minor' => 'integer',
            'exchange_rate' => 'decimal:6',
            'foreign_amount' => 'decimal:2',
            'foreign_amount_minor' => 'integer',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'debit' => 'debit_minor',
            'credit' => 'credit_minor',
            'foreign_amount' => 'foreign_amount_minor',
        ];
    }

    /**
     * No `business` relation of its own — business_id is a plain column
     * here (JournalEntry, not JournalLine, uses BelongsToTenant), so
     * resolve currency with a direct lookup rather than a relation chain.
     */
    protected function moneyMinorCurrency(): string
    {
        return Business::query()->find($this->business_id)?->currency ?? 'TZS';
    }
}
