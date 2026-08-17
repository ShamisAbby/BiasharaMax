<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductNote extends Model
{
    use BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'product_id',
        'note',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
