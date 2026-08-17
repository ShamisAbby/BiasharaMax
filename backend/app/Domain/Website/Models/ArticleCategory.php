<?php

namespace App\Domain\Website\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleCategory extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'category_id');
    }
}
