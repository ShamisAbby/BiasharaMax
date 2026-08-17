<?php

namespace App\Domain\Website\Models;

use App\Domain\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEnquiry extends Model
{
    use HasUuids;

    public const STATUS_NEW = 'new';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_CLOSED = 'closed';

    protected $attributes = [
        'status' => self::STATUS_NEW,
    ];

    protected $fillable = [
        'business_id',
        'product_id',
        'name',
        'email',
        'phone',
        'message',
        'status',
        'reply',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
