<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'business_id',
        'base_unit_id',
        'name',
        'symbol',
        'conversion_factor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
        ];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function derivedUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'base_unit_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
