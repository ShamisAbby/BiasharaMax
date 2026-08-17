<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductVariant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivedItem extends Model
{
    use HasUuids;

    protected $attributes = [
        'quantity_received' => 0,
        'quantity_damaged' => 0,
        'quantity_rejected' => 0,
    ];

    protected $fillable = [
        'goods_received_note_id',
        'purchase_order_item_id',
        'product_id',
        'product_variant_id',
        'quantity_received',
        'quantity_damaged',
        'quantity_rejected',
        'batch_number',
        'manufactured_date',
        'expiry_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:3',
            'quantity_damaged' => 'decimal:3',
            'quantity_rejected' => 'decimal:3',
            'manufactured_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Total units accounted for from this delivery, whether or not they
     * entered sellable stock — used to settle the PO line's
     * quantity_received even though only the good portion increases
     * Inventory.quantity.
     */
    public function totalProcessedQuantity(): string
    {
        return bcadd(
            bcadd((string) $this->quantity_received, (string) $this->quantity_damaged, 3),
            (string) $this->quantity_rejected,
            3,
        );
    }
}
