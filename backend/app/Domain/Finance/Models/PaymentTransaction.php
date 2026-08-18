<?php

namespace App\Domain\Finance\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\Business;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Platform-wide payment ledger. See the migration docblock for why this
 * is a separate table from the pre-existing `subscription_transactions`.
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PaymentTransaction extends Model
{
    use Auditable, HasFactory, HasUserstamps, HasUuids, SyncsMoneyMinorColumns;

    public const TYPE_SUBSCRIPTION_PAYMENT = 'subscription_payment';

    public const TYPE_LICENSE_PAYMENT = 'license_payment';

    public const TYPE_RENEWAL = 'renewal';

    public const TYPE_UPGRADE = 'upgrade';

    public const TYPE_MANUAL = 'manual';

    public const TYPE_REFUND = 'refund';

    public const TYPE_PARTIAL_REFUND = 'partial_refund';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESSFUL = 'successful';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const STATUS_EXPIRED = 'expired';

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CREDIT_CARD = 'credit_card';

    public const METHOD_DEBIT_CARD = 'debit_card';

    public const METHOD_MOBILE_MONEY = 'mobile_money';

    public const METHOD_PAYPAL = 'paypal';

    public const METHOD_STRIPE = 'stripe';

    public const METHOD_FLUTTERWAVE = 'flutterwave';

    public const METHOD_PESAPAL = 'pesapal';

    public const METHOD_SNIPPE = 'snippe';

    public const METHOD_CUSTOM = 'custom';

    protected $attributes = [
        'tax_amount' => 0,
        'tax_amount_minor' => 0,
        'discount_amount' => 0,
        'discount_amount_minor' => 0,
        'fee_amount' => 0,
        'fee_amount_minor' => 0,
        'commission_amount' => 0,
        'commission_amount_minor' => 0,
        'refunded_amount' => 0,
        'refunded_amount_minor' => 0,
    ];

    protected $fillable = [
        'business_id',
        'payment_gateway_id',
        'payable_id',
        'payable_type',
        'parent_transaction_id',
        'type',
        'reference_number',
        'invoice_number',
        'external_transaction_id',
        'amount',
        'amount_minor',
        'currency',
        'tax_amount',
        'tax_amount_minor',
        'discount_amount',
        'discount_amount_minor',
        'fee_amount',
        'fee_amount_minor',
        'commission_amount',
        'commission_amount_minor',
        'refunded_amount',
        'refunded_amount_minor',
        'status',
        'payment_method',
        'receipt_path',
        'notes',
        'metadata',
        'initiated_by',
        'paid_at',
        'failed_at',
        'refunded_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'tax_amount' => 'decimal:2',
            'tax_amount_minor' => 'integer',
            'discount_amount' => 'decimal:2',
            'discount_amount_minor' => 'integer',
            'fee_amount' => 'decimal:2',
            'fee_amount_minor' => 'integer',
            'commission_amount' => 'decimal:2',
            'commission_amount_minor' => 'integer',
            'refunded_amount' => 'decimal:2',
            'refunded_amount_minor' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PaymentTransactionFactory
    {
        return PaymentTransactionFactory::new();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'initiated_by');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(PaymentTransactionLog::class)->latest('created_at');
    }

    public function gatewayLogs(): HasMany
    {
        return $this->hasMany(PaymentGatewayLog::class);
    }

    public function netAmount(): string
    {
        return bcsub(
            bcsub($this->amount, $this->fee_amount, 2),
            $this->commission_amount,
            2,
        );
    }

    public function isRefundable(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESSFUL, self::STATUS_PARTIALLY_REFUNDED], true)
            && bccomp($this->refunded_amount, $this->amount, 2) < 0;
    }

    /**
     * @return array<string, string>
     */
    /**
     * `created_by`, `updated_by` and `initiated_by` are all foreign-keyed
     * to `platform_users` (see the create migration), so only a platform
     * admin may be stamped here. A vendor paying for their own
     * subscription is recorded in `metadata` instead.
     */
    protected static function userstampGuard(): ?string
    {
        return 'platform';
    }

    protected function moneyMinorColumns(): array
    {
        return [
            'amount' => 'amount_minor',
            'tax_amount' => 'tax_amount_minor',
            'discount_amount' => 'discount_amount_minor',
            'fee_amount' => 'fee_amount_minor',
            'commission_amount' => 'commission_amount_minor',
            'refunded_amount' => 'refunded_amount_minor',
        ];
    }

    /**
     * This row carries its own `currency` column (a transaction's currency
     * is fixed at the time it happened, and shouldn't drift if the
     * business's default currency setting changes later) — use it
     * directly rather than resolving via the business relation.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->currency ?? $this->business?->currency ?? 'TZS';
    }
}
