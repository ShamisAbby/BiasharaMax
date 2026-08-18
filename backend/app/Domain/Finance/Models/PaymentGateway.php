<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Database\Factories\PaymentGatewayFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PaymentGateway extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const PROVIDER_STRIPE = 'stripe';

    public const PROVIDER_SNIPPE = 'snippe';

    public const PROVIDER_PESAPAL = 'pesapal';

    public const PROVIDER_FLUTTERWAVE = 'flutterwave';

    public const PROVIDER_PAYPAL = 'paypal';

    public const PROVIDER_BANK_TRANSFER = 'bank_transfer';

    public const PROVIDER_MPESA = 'mpesa';

    public const PROVIDER_AIRTEL_MONEY = 'airtel_money';

    public const PROVIDER_TIGO_PESA = 'tigo_pesa';

    public const PROVIDER_HALOPESA = 'halopesa';

    public const PROVIDER_MIXX_BY_YAS = 'mixx_by_yas';

    public const PROVIDER_CASH = 'cash';

    public const PROVIDER_CUSTOM = 'custom';

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_PRODUCTION = 'production';

    public const HEALTH_ONLINE = 'online';

    public const HEALTH_OFFLINE = 'offline';

    public const HEALTH_DEGRADED = 'degraded';

    public const HEALTH_UNKNOWN = 'unknown';

    protected $attributes = [
        'is_enabled' => false,
        'mode' => self::MODE_SANDBOX,
        'fee_percentage' => 0,
        'fee_fixed' => 0,
        'fee_fixed_minor' => 0,
        'priority' => 0,
        'health_status' => self::HEALTH_UNKNOWN,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'name',
        'slug',
        'provider',
        'is_enabled',
        'mode',
        'credentials',
        'webhook_url',
        'webhook_secret',
        'supported_currencies',
        'supported_countries',
        'fee_percentage',
        'fee_fixed',
        'fee_fixed_minor',
        'priority',
        'health_status',
        'last_health_check_at',
        'documentation_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'supported_currencies' => 'array',
            'supported_countries' => 'array',
            'fee_percentage' => 'decimal:2',
            'fee_fixed' => 'decimal:2',
            'fee_fixed_minor' => 'integer',
            'last_health_check_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PaymentGatewayFactory
    {
        return PaymentGatewayFactory::new();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentGatewayLog::class);
    }

    /**
     * A gateway is only actually chargeable once it's enabled AND has at
     * least one credential set — a freshly-registered, unconfigured
     * gateway must never silently pretend to process money.
     */
    /**
     * Does this gateway have the credentials it needs?
     *
     * Deliberately says nothing about whether it is switched on. Those are
     * two separate facts and combining them created a deadlock that made
     * every gateway impossible to enable:
     *
     *   - the admin refuses to enable a gateway that is not configured
     *   - `isConfigured()` required `is_enabled` to be true
     *
     * So a freshly configured gateway reported "Not configured" no matter
     * how many times its keys were saved, and the only fix appeared to be
     * the thing that was already blocked. Nobody could enable Stripe,
     * Snippe or any of the other ten.
     *
     * Both readings of the word were defensible in isolation, which is
     * what made it survive: the admin meant "has keys", the driver meant
     * "ready to take money". They are now separate methods.
     */
    public function isConfigured(): bool
    {
        return filled($this->credentials);
    }

    /**
     * Configured AND switched on — the only state in which money moves.
     *
     * This is what the drivers check. A disabled gateway must never take a
     * charge, however complete its credentials are.
     */
    public function isUsable(): bool
    {
        return $this->is_enabled && $this->isConfigured();
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['fee_fixed' => 'fee_fixed_minor'];
    }

    /**
     * A gateway is a platform-wide config row, not tied to a single
     * business (it has no business_id and no per-row currency column,
     * unlike PaymentTransaction) — falls back to the platform's own
     * default currency (see App\Domain\Settings\Services\PlatformSettingsService)
     * rather than a business relation that doesn't exist here.
     */
    protected function moneyMinorCurrency(): string
    {
        return 'TZS';
    }
}
