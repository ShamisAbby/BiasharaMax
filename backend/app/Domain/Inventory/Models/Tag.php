<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tag')->withTimestamps();
    }
}
