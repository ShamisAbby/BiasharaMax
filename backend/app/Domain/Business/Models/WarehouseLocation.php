<?php

namespace App\Domain\Business\Models;

use App\Domain\Inventory\Models\Inventory;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Optional aisle/shelf/bin-level granularity within a Warehouse. Most
 * businesses won't use this; hardware/wholesale operations that need
 * precise put-away locations will.
 */
class WarehouseLocation extends Model
{
    use BelongsToTenant, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'business_id',
        'warehouse_id',
        'parent_location_id',
        'name',
        'code',
        'status',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function parentLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'parent_location_id');
    }

    public function childLocations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'parent_location_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
