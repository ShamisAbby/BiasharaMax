<?php

namespace App\Domain\CRM\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoyaltyReward extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'description',
        'points_cost',
        'stock_quantity',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRewardRedemption::class);
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity === null || $this->stock_quantity > 0;
    }
}
