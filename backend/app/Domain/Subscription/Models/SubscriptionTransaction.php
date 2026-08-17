<?php

namespace App\Domain\Subscription\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\Business;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Database\Factories\SubscriptionTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manually-recorded payment against a subscription. BiasharaMax has no
 * payment gateway integration yet (see PlatformAnalyticsService and the
 * Payment Gateways "Soon" nav item) — a SuperAdmin records what was
 * actually paid outside the system, so this is real history, not a
 * projection.
 */
class SubscriptionTransaction extends Model
{
    use Auditable, HasFactory, HasUuids, SyncsMoneyMinorColumns;

    public const STATUS_PAID = 'paid';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'business_id',
        'subscription_id',
        'amount',
        'amount_minor',
        'currency',
        'billing_cycle',
        'status',
        'payment_method',
        'notes',
        'recorded_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'recorded_by');
    }

    protected static function newFactory(): SubscriptionTransactionFactory
    {
        return SubscriptionTransactionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['amount' => 'amount_minor'];
    }

    /**
     * This row carries its own `currency` column (a historical record of
     * what was actually paid, which shouldn't drift if the business's
     * default currency setting changes later) — use it directly, falling
     * back to the business relation, matching PaymentTransaction's pattern.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->currency ?? $this->business?->currency ?? 'TZS';
    }
}
