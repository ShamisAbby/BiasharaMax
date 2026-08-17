<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'business_id',
        'product_id',
        'sku',
        'barcode',
        'attributes',
        'cost_price',
        'cost_price_minor',
        'selling_price',
        'selling_price_minor',
        'wholesale_price',
        'wholesale_price_minor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'cost_price' => 'decimal:2',
            'cost_price_minor' => 'integer',
            'selling_price' => 'decimal:2',
            'selling_price_minor' => 'integer',
            'wholesale_price' => 'decimal:2',
            'wholesale_price_minor' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'product_variant_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_variant_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'product_variant_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ProductSerial::class, 'product_variant_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'product_variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_variant_id');
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'cost_price' => 'cost_price_minor',
            'selling_price' => 'selling_price_minor',
            'wholesale_price' => 'wholesale_price_minor',
        ];
    }
}
