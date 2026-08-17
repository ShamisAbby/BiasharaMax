<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant isolation is enforced transitively through the parent Attribute
 * (this table has no business_id column of its own).
 */
class AttributeValue extends Model
{
    use HasUuids;

    protected $fillable = [
        'attribute_id',
        'value',
        'sort_order',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
