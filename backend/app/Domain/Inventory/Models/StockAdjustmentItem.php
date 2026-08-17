<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\SyncsMoneyMicroColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    use HasUuids, SyncsMoneyMicroColumns;

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'product_variant_id',
        'product_batch_id',
        'direction',
        'quantity',
        'unit_cost',
        'unit_cost_micros',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'unit_cost_micros' => 'integer',
        ];
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMicroColumns(): array
    {
        return ['unit_cost' => 'unit_cost_micros'];
    }
}
