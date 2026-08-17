<?php

namespace App\Domain\Business\Models;

use App\Domain\ModuleManagement\Models\Module;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Database\Factories\BusinessTypeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `website_template` and the `default_*_limit` columns are intentionally
 * unenforced metadata — see the migration's docblock. Subscription Plan
 * limits remain the single enforced ceiling (SubscriptionLimitService);
 * these are defaults/suggestions only.
 */
class BusinessType extends Model
{
    use Auditable, HasFactory, HasUserstamps, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'description',
        'default_currency',
        'default_tax_rate',
        'default_units',
        'website_template',
        'inventory_enabled',
        'pos_enabled',
        'accounting_enabled',
        'crm_enabled',
        'website_enabled',
        'online_ordering_enabled',
        'offline_mode_enabled',
        'desktop_edition_enabled',
        'default_employee_limit',
        'default_branch_limit',
        'default_warehouse_limit',
        'default_storage_limit_mb',
        'status',
        'sort_order',
        'website_template_id',
    ];

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
            'default_units' => 'array',
            'inventory_enabled' => 'boolean',
            'pos_enabled' => 'boolean',
            'accounting_enabled' => 'boolean',
            'crm_enabled' => 'boolean',
            'website_enabled' => 'boolean',
            'online_ordering_enabled' => 'boolean',
            'offline_mode_enabled' => 'boolean',
            'desktop_edition_enabled' => 'boolean',
        ];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'business_type_subscription_plan');
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'business_type_module');
    }

    public function websiteTemplate(): BelongsTo
    {
        return $this->belongsTo(WebsiteTemplate::class);
    }

    protected static function newFactory(): BusinessTypeFactory
    {
        return BusinessTypeFactory::new();
    }
}
