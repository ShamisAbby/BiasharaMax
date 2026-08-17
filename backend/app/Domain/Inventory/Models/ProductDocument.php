<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDocument extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'business_id',
        'product_id',
        'name',
        'file_path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
