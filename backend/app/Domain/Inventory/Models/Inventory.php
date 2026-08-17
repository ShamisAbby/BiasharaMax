<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Business\Models\WarehouseLocation;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMicroColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The live, denormalized stock level for one (warehouse, product, variant)
 * combination. This is a read-optimized snapshot; the authoritative,
 * append-only history of how it got there lives in StockMovement. Not
 * Auditable on purpose — auditing every quantity tick here would just
 * duplicate what StockMovement already records precisely.
 */
class Inventory extends Model
{
    use BelongsToTenant, HasUserstamps, HasUuids, SyncsMoneyMicroColumns;

    protected $fillable = [
        'business_id',
        'branch_id',
        'warehouse_id',
        'warehouse_location_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'reserved_quantity',
        'minimum_stock',
        'maximum_stock',
        'reorder_level',
        'average_cost',
        'average_cost_micros',
        'last_counted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'average_cost' => 'decimal:4',
            'average_cost_micros' => 'integer',
            'last_counted_at' => 'datetime',
        ];
    }

    public function getAvailableQuantityAttribute(): string
    {
        return bcsub((string) $this->quantity, (string) $this->reserved_quantity, 3);
    }

    public const LOW_STOCK_THRESHOLD = 10;

    public function isLowStock(): bool
    {
        return (float) $this->quantity > 0 && (float) $this->quantity < self::LOW_STOCK_THRESHOLD;
    }

    public function isOutOfStock(): bool
    {
        return (float) $this->quantity <= 0;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMicroColumns(): array
    {
        return ['average_cost' => 'average_cost_micros'];
    }
}
