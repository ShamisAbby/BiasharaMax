<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Business\Models\Warehouse;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockReservation extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RELEASED = 'released';

    public const STATUS_FULFILLED = 'fulfilled';

    protected $fillable = [
        'business_id',
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'reference_type',
        'reference_id',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
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

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
