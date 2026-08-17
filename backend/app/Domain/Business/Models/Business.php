<?php

namespace App\Domain\Business\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Inventory\Models\Brand;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Collection as ProductCollection;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\Tag;
use App\Domain\Inventory\Models\Unit;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\RBAC\Models\Role;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use Auditable, HasUserstamps, HasUuids, SoftDeletes;

    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'name',
        'slug',
        'business_type',
        'business_type_id',
        'email',
        'phone',
        'country',
        'currency',
        'timezone',
        'address',
        'city',
        'logo_path',
        'owner_id',
        'status',
        'trial_ends_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function productCollections(): HasMany
    {
        return $this->hasMany(ProductCollection::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function isOnTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }
}
