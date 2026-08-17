<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'inventory_count_id',
        'product_id',
        'product_variant_id',
        'expected_quantity',
        'counted_quantity',
        'variance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'variance' => 'decimal:3',
        ];
    }

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
