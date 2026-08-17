<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A curated merchandising group (e.g. "Back to School"), distinct from
 * Category which is a structural classification.
 */
class Collection extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'description',
        'image_path',
        'status',
        'sort_order',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_collection')->withTimestamps();
    }
}
