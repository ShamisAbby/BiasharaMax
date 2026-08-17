<?php

namespace App\Domain\Website\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessWebsite extends Model
{
    use Auditable, HasUserstamps, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'business_id',
        'website_template_id',
        'status',
        'seo_title',
        'seo_description',
        'published_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WebsiteTemplate::class, 'website_template_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(BusinessWebsitePage::class)->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
