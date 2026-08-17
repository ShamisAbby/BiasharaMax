<?php

namespace App\Domain\WebsiteTemplates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteTemplatePage extends Model
{
    use HasUuids;

    protected $fillable = [
        'website_template_id',
        'type',
        'title',
        'slug',
        'content',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(WebsiteTemplate::class, 'website_template_id');
    }
}
