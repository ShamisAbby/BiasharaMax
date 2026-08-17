<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Business\Models\Warehouse;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBatch extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DEPLETED = 'depleted';

    protected $fillable = [
        'business_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'batch_number',
        'manufactured_date',
        'expiry_date',
        'quantity',
        'cost_price',
        'cost_price_minor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'manufactured_date' => 'date',
            'expiry_date' => 'date',
            'quantity' => 'decimal:3',
            'cost_price' => 'decimal:2',
            'cost_price_minor' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $withinDays = 30): bool
    {
        return $this->expiry_date !== null
            && ! $this->isExpired()
            && $this->expiry_date->isBefore(now()->addDays($withinDays));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_batch_id');
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['cost_price' => 'cost_price_minor'];
    }
}
