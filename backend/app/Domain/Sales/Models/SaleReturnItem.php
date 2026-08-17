<?php

namespace App\Domain\Sales\Models;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Inventory\Models\ProductVariant;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    public const CONDITION_GOOD = 'good';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_EXPIRED = 'expired';

    protected $attributes = [
        'condition' => self::CONDITION_GOOD,
        'restock' => true,
    ];

    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'product_variant_id',
        'product_batch_id',
        'quantity_returned',
        'condition',
        'restock',
        'unit_price',
        'unit_price_minor',
        'line_refund_amount',
        'line_refund_amount_minor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_returned' => 'decimal:3',
            'restock' => 'boolean',
            'unit_price' => 'decimal:2',
            'unit_price_minor' => 'integer',
            'line_refund_amount' => 'decimal:2',
            'line_refund_amount_minor' => 'integer',
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
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
    protected function moneyMinorColumns(): array
    {
        return [
            'unit_price' => 'unit_price_minor',
            'line_refund_amount' => 'line_refund_amount_minor',
        ];
    }

    /**
     * No `business` relation of its own — resolve currency via the
     * return it belongs to instead.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->saleReturn?->business?->currency ?? 'TZS';
    }
}
