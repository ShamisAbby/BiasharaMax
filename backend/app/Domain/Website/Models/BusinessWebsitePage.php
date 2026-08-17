<?php

namespace App\Domain\Website\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessWebsitePage extends Model
{
    use HasUuids;

    protected $attributes = [
        'is_enabled' => true,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'business_website_id',
        'type',
        'title',
        'slug',
        'content',
        'seo_title',
        'seo_description',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(BusinessWebsite::class, 'business_website_id');
    }
}
