<?php

namespace App\Domain\Sales\Models;

use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Inventory\Models\ProductVariant;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_variant_id',
        'product_batch_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'unit_price_minor',
        'unit_cost',
        'unit_cost_minor',
        'discount_amount',
        'discount_amount_minor',
        'tax_amount',
        'tax_amount_minor',
        'line_total',
        'line_total_minor',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'unit_price_minor' => 'integer',
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'unit_price' => 'unit_price_minor',
            'unit_cost' => 'unit_cost_minor',
            'discount_amount' => 'discount_amount_minor',
            'tax_amount' => 'tax_amount_minor',
            'line_total' => 'line_total_minor',
        ];
    }

    /**
     * No `business` relation of its own — resolve currency via the sale
     * it belongs to instead.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->sale?->business?->currency ?? 'TZS';
    }
}
