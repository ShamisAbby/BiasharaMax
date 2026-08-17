<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\SyncsMoneyMicroColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasUuids, SyncsMoneyMicroColumns;

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'product_variant_id',
        'product_batch_id',
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

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
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
