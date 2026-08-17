<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Models\Pivots\ProductSupplier;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const TYPE_SIMPLE = 'simple';

    public const TYPE_VARIABLE = 'variable';

    public const TYPE_SERVICE = 'service';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const VISIBILITY_VISIBLE = 'visible';

    public const VISIBILITY_HIDDEN = 'hidden';

    protected $fillable = [
        'business_id',
        'category_id',
        'brand_id',
        'unit_id',
        'default_supplier_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'custom_code',
        'description',
        'product_type',
        'track_stock',
        'has_expiry',
        'has_batch',
        'has_serial',
        'cost_price',
        'cost_price_minor',
        'purchase_price',
        'purchase_price_minor',
        'selling_price',
        'selling_price_minor',
        'wholesale_price',
        'wholesale_price_minor',
        'minimum_price',
        'minimum_price_minor',
        'tax_rate',
        'last_purchase_price',
        'last_purchase_price_minor',
        'last_purchase_at',
        'minimum_stock',
        'maximum_stock',
        'reorder_level',
        'weight',
        'weight_unit',
        'dimensions',
        'status',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'track_stock' => 'boolean',
            'has_expiry' => 'boolean',
            'has_batch' => 'boolean',
            'has_serial' => 'boolean',
            'cost_price' => 'decimal:2',
            'cost_price_minor' => 'integer',
            'purchase_price' => 'decimal:2',
            'purchase_price_minor' => 'integer',
            'selling_price' => 'decimal:2',
            'selling_price_minor' => 'integer',
            'wholesale_price' => 'decimal:2',
            'wholesale_price_minor' => 'integer',
            'minimum_price' => 'decimal:2',
            'minimum_price_minor' => 'integer',
            'tax_rate' => 'decimal:2',
            'last_purchase_price' => 'decimal:2',
            'last_purchase_price_minor' => 'integer',
            'last_purchase_at' => 'datetime',
            'minimum_stock' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'weight' => 'decimal:3',
            'dimensions' => 'array',
        ];
    }

    public function isVariable(): bool
    {
        return $this->product_type === self::TYPE_VARIABLE;
    }

    public function tracksStock(): bool
    {
        return $this->track_stock && $this->product_type !== self::TYPE_SERVICE;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
            ->using(ProductSupplier::class)
            ->withPivot(['supplier_sku', 'supplier_cost_price'])
            ->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProductNote::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag')->withTimestamps();
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'product_collection')->withTimestamps();
    }

    /**
     * Attribute values that apply to the base product (not to a specific
     * variant) — e.g. "Material: Cotton" on a simple product.
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ProductSerial::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'cost_price' => 'cost_price_minor',
            'purchase_price' => 'purchase_price_minor',
            'selling_price' => 'selling_price_minor',
            'wholesale_price' => 'wholesale_price_minor',
            'minimum_price' => 'minimum_price_minor',
            'last_purchase_price' => 'last_purchase_price_minor',
        ];
    }
}
