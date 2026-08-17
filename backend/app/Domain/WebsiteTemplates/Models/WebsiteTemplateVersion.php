<?php

namespace App\Domain\WebsiteTemplates\Models;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteTemplateVersion extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'website_template_id',
        'version',
        'snapshot',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WebsiteTemplate::class, 'website_template_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'published_by');
    }
}
