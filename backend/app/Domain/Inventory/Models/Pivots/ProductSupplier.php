<?php

namespace App\Domain\Inventory\Models\Pivots;

use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductSupplier extends Pivot
{
    use SyncsMoneyMinorColumns;

    protected $table = 'product_supplier';

    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'supplier_id',
        'supplier_sku',
        'supplier_cost_price',
        'supplier_cost_price_minor',
    ];

    protected function casts(): array
    {
        return [
            'supplier_cost_price' => 'decimal:2',
            'supplier_cost_price_minor' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['supplier_cost_price' => 'supplier_cost_price_minor'];
    }

    /**
     * No `business` relation of its own (and no business_id column on the
     * pivot) — resolve currency via the product side.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->product?->business?->currency ?? 'TZS';
    }
}
