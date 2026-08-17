<?php

namespace App\Domain\Business\Models;

use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Inventory\Models\ProductSerial;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'branch_id',
        'name',
        'code',
        'is_default',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function productSerials(): HasMany
    {
        return $this->hasMany(ProductSerial::class);
    }
}
