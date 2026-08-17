<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    public const UPDATED_AT = null;

    public const TYPE_DEBIT = 'debit';

    public const TYPE_CREDIT = 'credit';

    public const TYPE_TRANSFER = 'transfer';

    public const STATUS_UNRECONCILED = 'unreconciled';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'business_id',
        'bank_account_id',
        'journal_entry_id',
        'transaction_date',
        'type',
        'amount',
        'amount_minor',
        'reference',
        'description',
        'reconciliation_status',
        'reconciled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'transaction_date' => 'date',
            'reconciled_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isReconciled(): bool
    {
        return $this->reconciliation_status === self::STATUS_RECONCILED;
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['amount' => 'amount_minor'];
    }

    /**
     * No `business` relation of its own — resolve currency via the bank
     * account it belongs to instead.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->bankAccount?->business?->currency ?? 'TZS';
    }
}
