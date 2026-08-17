<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductVariant;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_sku',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'unit_cost_minor',
        'discount_amount',
        'discount_amount_minor',
        'tax_amount',
        'tax_amount_minor',
        'line_total',
        'line_total_minor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'unit_cost_minor' => 'integer',
            'discount_amount' => 'decimal:2',
            'discount_amount_minor' => 'integer',
            'tax_amount' => 'decimal:2',
            'tax_amount_minor' => 'integer',
            'line_total' => 'decimal:2',
            'line_total_minor' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function goodsReceivedItems(): HasMany
    {
        return $this->hasMany(GoodsReceivedItem::class);
    }

    /**
     * Quantity still owed by the supplier — never negative, since
     * quantity_received can equal but not (validly) exceed quantity_ordered.
     */
    public function remainingQuantity(): string
    {
        $remaining = bcsub((string) $this->quantity_ordered, (string) $this->quantity_received, 3);

        return bccomp($remaining, '0', 3) > 0 ? $remaining : '0.000';
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'unit_cost' => 'unit_cost_minor',
            'discount_amount' => 'discount_amount_minor',
            'tax_amount' => 'tax_amount_minor',
            'line_total' => 'line_total_minor',
        ];
    }

    /**
     * No `business` relation of its own — resolve currency via the
     * purchase order it belongs to instead.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->purchaseOrder?->supplier?->business?->currency ?? 'TZS';
    }
}
