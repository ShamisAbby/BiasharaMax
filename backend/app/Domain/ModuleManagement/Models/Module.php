<?php

namespace App\Domain\ModuleManagement\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The platform-wide feature registry. Rows for modules that aren't
 * actually built yet (POS, CRM, AI Assistant, ...) are honest metadata
 * only — toggling them has no real effect since nothing exists to gate.
 * Real, gateable modules today: inventory, subscriptions, licensing,
 * audit-logs, business (branches/warehouses), employees-rbac.
 */
class Module extends Model
{
    use Auditable, HasFactory, HasUserstamps, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_DEPRECATED = 'deprecated';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_HIDDEN = 'hidden';

    public const VISIBILITY_BETA = 'beta';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'icon',
        'category',
        'is_premium',
        'status',
        'visibility',
        'is_desktop_supported',
        'is_cloud_supported',
        'is_hybrid_supported',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_premium' => 'boolean',
            'is_desktop_supported' => 'boolean',
            'is_cloud_supported' => 'boolean',
            'is_hybrid_supported' => 'boolean',
        ];
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'module_dependencies', 'module_id', 'depends_on_module_id');
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'module_dependencies', 'depends_on_module_id', 'module_id');
    }

    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'module_subscription_plan');
    }

    public function businessTypes(): BelongsToMany
    {
        return $this->belongsToMany(BusinessType::class, 'business_type_module');
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_module')
            ->withPivot(['is_enabled', 'installed_at', 'uninstalled_at']);
    }

    public function versionHistory(): HasMany
    {
        return $this->hasMany(ModuleVersionHistory::class);
    }

    protected static function newFactory(): ModuleFactory
    {
        return ModuleFactory::new();
    }
}
