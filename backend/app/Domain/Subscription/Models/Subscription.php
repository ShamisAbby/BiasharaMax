<?php

namespace App\Domain\Subscription\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    use Auditable, HasUserstamps, HasUuids, SyncsMoneyMinorColumns;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUSPENDED = 'suspended';

    /** Days of continued access granted after a subscription lapses. */
    public const GRACE_PERIOD_DAYS = 7;

    protected $fillable = [
        'business_id',
        'subscription_plan_id',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'grace_period_ends_at',
        'custom_price',
        'custom_price_minor',
        'custom_limits',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'custom_price' => 'decimal:2',
            'custom_price_minor' => 'integer',
            'custom_limits' => 'array',
            'auto_renew' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class);
    }

    public function paymentTransactions(): MorphMany
    {
        return $this->morphMany(PaymentTransaction::class, 'payable');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true);
    }

    public function isInGracePeriod(): bool
    {
        return $this->grace_period_ends_at !== null && $this->grace_period_ends_at->isFuture();
    }

    /**
     * True once both the billing/trial period AND the grace period have
     * lapsed — this is the point a business actually loses access.
     */
    public function isLocked(): bool
    {
        if (in_array($this->status, [self::STATUS_SUSPENDED, self::STATUS_CANCELED], true)) {
            return true;
        }

        // Trials end immediately — no grace period for unconverted trials.
        if ($this->status === self::STATUS_TRIALING) {
            return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
        }

        // A paying customer's period lapsing gets a grace window — computed
        // live so this is accurate even before the daily command has run.
        if ($this->status === self::STATUS_ACTIVE && $this->current_period_end?->isPast()) {
            $graceEnd = $this->grace_period_ends_at
                ?? $this->current_period_end->copy()->addDays(self::GRACE_PERIOD_DAYS);

            return $graceEnd->isPast();
        }

        if ($this->status === self::STATUS_EXPIRED) {
            return ! $this->isInGracePeriod();
        }

        return false;
    }

    public function effectivePrice(): float
    {
        if ($this->custom_price !== null) {
            return (float) $this->custom_price;
        }

        if ($this->billing_cycle === null) {
            return 0.0;
        }

        return $this->plan->priceFor($this->billing_cycle);
    }

    /**
     * @return array<string, int|null>
     */
    public function effectiveLimits(): array
    {
        $planLimits = $this->plan->limits();

        if ($this->custom_limits === null) {
            return $planLimits;
        }

        return array_merge($planLimits, $this->custom_limits);
    }

    public function startGracePeriod(): void
    {
        $this->grace_period_ends_at = Carbon::now()->addDays(self::GRACE_PERIOD_DAYS);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['custom_price' => 'custom_price_minor'];
    }
}
