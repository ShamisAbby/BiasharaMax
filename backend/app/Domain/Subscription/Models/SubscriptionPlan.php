<?php

namespace App\Domain\Subscription\Models;

use App\Domain\Business\Models\BusinessType;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory, HasUuids, SyncsMoneyMinorColumns;

    public const TYPE_STANDARD = 'standard';

    public const TYPE_ENTERPRISE = 'enterprise';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'price_monthly',
        'price_monthly_minor',
        'price_quarterly',
        'price_quarterly_minor',
        'price_yearly',
        'price_yearly_minor',
        'price_lifetime',
        'price_lifetime_minor',
        'trial_days',
        'features',
        'is_active',
        'sort_order',
        'max_users',
        'max_branches',
        'max_warehouses',
        'max_products',
        'max_employees',
        'max_storage_mb',
        'max_api_requests_per_day',
        'max_notifications_per_month',
        'includes_website',
        'includes_ai',
        'includes_offline_sync',
        'includes_desktop_edition',
        'includes_reports',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_monthly_minor' => 'integer',
            'price_quarterly' => 'decimal:2',
            'price_quarterly_minor' => 'integer',
            'price_yearly' => 'decimal:2',
            'price_yearly_minor' => 'integer',
            'price_lifetime' => 'decimal:2',
            'price_lifetime_minor' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'includes_website' => 'boolean',
            'includes_ai' => 'boolean',
            'includes_offline_sync' => 'boolean',
            'includes_desktop_edition' => 'boolean',
            'includes_reports' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_subscription_plan');
    }

    public function businessTypes(): BelongsToMany
    {
        return $this->belongsToMany(BusinessType::class, 'business_type_subscription_plan');
    }

    public function priceFor(string $billingCycle): float
    {
        return match ($billingCycle) {
            'monthly' => (float) $this->price_monthly,
            'quarterly' => (float) $this->price_quarterly,
            'yearly' => (float) $this->price_yearly,
            'lifetime' => (float) $this->price_lifetime,
            default => throw new \InvalidArgumentException("Unknown billing cycle [{$billingCycle}]."),
        };
    }

    /**
     * @return array<string, int|null>
     */
    public function limits(): array
    {
        return [
            'max_users' => $this->max_users,
            'max_branches' => $this->max_branches,
            'max_warehouses' => $this->max_warehouses,
            'max_products' => $this->max_products,
            'max_employees' => $this->max_employees,
            'max_storage_mb' => $this->max_storage_mb,
            'max_api_requests_per_day' => $this->max_api_requests_per_day,
            'max_notifications_per_month' => $this->max_notifications_per_month,
        ];
    }

    protected static function newFactory(): SubscriptionPlanFactory
    {
        return SubscriptionPlanFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'price_monthly' => 'price_monthly_minor',
            'price_quarterly' => 'price_quarterly_minor',
            'price_yearly' => 'price_yearly_minor',
            'price_lifetime' => 'price_lifetime_minor',
        ];
    }

    /**
     * A subscription plan is a platform-wide catalog row, not tied to a
     * single business — no business_id/business relation exists here, so
     * this falls back to the platform's default currency (same convention
     * as PaymentGateway, which has the same platform-wide shape).
     */
    protected function moneyMinorCurrency(): string
    {
        return 'TZS';
    }
}
