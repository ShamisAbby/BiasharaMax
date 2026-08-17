<?php

namespace App\Domain\Support\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseCategory extends Model
{
    use HasUuids;

    protected $attributes = [
        'sort_order' => 0,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticle::class);
    }
}
