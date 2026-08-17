<?php

namespace App\Domain\Sales\Models;

use App\Domain\CRM\Models\CustomerGroup;
use App\Domain\CRM\Models\CustomerLoyaltyTransaction;
use App\Domain\CRM\Models\CustomerNote;
use App\Domain\CRM\Models\CustomerTag;
use App\Domain\CRM\Models\LoyaltyTier;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const TYPE_CASH = 'cash';

    public const TYPE_CREDIT = 'credit';

    protected $attributes = [
        'customer_type' => self::TYPE_CASH,
        'credit_limit' => 0,
        'credit_limit_minor' => 0,
        'current_balance' => 0,
        'current_balance_minor' => 0,
        'loyalty_points' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'address',
        'city',
        'customer_type',
        'customer_group_id',
        'loyalty_tier_id',
        'credit_limit',
        'credit_limit_minor',
        'current_balance',
        'current_balance_minor',
        'loyalty_points',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'credit_limit_minor' => 'integer',
            'current_balance' => 'decimal:2',
            'current_balance_minor' => 'integer',
            'loyalty_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function currentBalanceMoney(?string $currency = null): Money
    {
        $currency ??= $this->business?->currency ?? 'TZS';

        return Money::fromMinorUnits($this->current_balance_minor ?? 0, $currency);
    }

    public function creditLimitMoney(?string $currency = null): Money
    {
        $currency ??= $this->business?->currency ?? 'TZS';

        return Money::fromMinorUnits($this->credit_limit_minor ?? 0, $currency);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function debtTransactions(): HasMany
    {
        return $this->hasMany(CustomerDebtTransaction::class)->latest('created_at');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function loyaltyTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'loyalty_tier_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_customer_tag');
    }

    public function crmNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest('created_at');
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyTransaction::class)->latest('created_at');
    }

    /**
     * How much more credit this customer can be extended before hitting
     * their limit. Cash-only customers have no credit facility at all.
     */
    public function availableCredit(): string
    {
        if ($this->customer_type !== self::TYPE_CREDIT) {
            return '0.00';
        }

        return $this->creditLimitMoney()->subtract($this->currentBalanceMoney())->toDecimalString();
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'credit_limit' => 'credit_limit_minor',
            'current_balance' => 'current_balance_minor',
        ];
    }
}
