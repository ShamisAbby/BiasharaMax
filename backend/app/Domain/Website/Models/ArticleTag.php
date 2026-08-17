<?php

namespace App\Domain\Website\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ArticleTag extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
    ];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_article_tag');
    }
}
