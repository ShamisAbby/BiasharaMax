<?php

namespace App\Domain\Finance\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliation extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'business_id',
        'bank_account_id',
        'period_start',
        'period_end',
        'statement_balance',
        'statement_balance_minor',
        'book_balance',
        'book_balance_minor',
        'difference',
        'difference_minor',
        'status',
        'reconciled_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'statement_balance' => 'decimal:2',
            'statement_balance_minor' => 'integer',
            'book_balance' => 'decimal:2',
            'book_balance_minor' => 'integer',
            'difference' => 'decimal:2',
            'difference_minor' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isBalanced(): bool
    {
        return bccomp((string) $this->difference, '0', 2) === 0;
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'statement_balance' => 'statement_balance_minor',
            'book_balance' => 'book_balance_minor',
            'difference' => 'difference_minor',
        ];
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
