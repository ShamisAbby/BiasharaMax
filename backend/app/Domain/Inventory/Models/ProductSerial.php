<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Business\Models\Warehouse;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSerial extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_SOLD = 'sold';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_DAMAGED = 'damaged';

    protected $fillable = [
        'business_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'serial_number',
        'status',
    ];

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
}
