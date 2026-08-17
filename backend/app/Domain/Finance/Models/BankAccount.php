<?php

namespace App\Domain\Finance\Models;

use App\Domain\Localization\Models\Currency;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    protected $fillable = [
        'business_id',
        'account_id',
        'bank_name',
        'account_number',
        'account_holder_name',
        'currency_id',
        'opening_balance',
        'opening_balance_minor',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opening_balance_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function currentBalance(): string
    {
        $debits = $this->bankTransactions()
            ->where('type', BankTransaction::TYPE_DEBIT)
            ->sum('amount');

        $credits = $this->bankTransactions()
            ->where('type', BankTransaction::TYPE_CREDIT)
            ->sum('amount');

        return bcsub((string) $debits, (string) $credits, 2);
    }

    public function lastReconciliation(): ?BankReconciliation
    {
        return $this->reconciliations()
            ->where('status', BankReconciliation::STATUS_COMPLETED)
            ->orderByDesc('period_end')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['opening_balance' => 'opening_balance_minor'];
    }
}
