<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const BALANCE_DEBIT = 'debit';

    public const BALANCE_CREDIT = 'credit';

    protected $attributes = [
        'is_system_default' => false,
        'is_active' => true,
    ];

    protected $fillable = [
        'business_id',
        'parent_account_id',
        'code',
        'name',
        'description',
        'type',
        'subtype',
        'normal_balance',
        'is_system_default',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_account_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isDebitNormal(): bool
    {
        return $this->normal_balance === self::BALANCE_DEBIT;
    }
}
