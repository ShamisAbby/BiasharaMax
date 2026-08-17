<?php

namespace App\Domain\Support\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Shared\Concerns\Auditable;
use Database\Factories\KnowledgeBaseArticleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeBaseArticle extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    public const TYPE_ARTICLE = 'article';

    public const TYPE_FAQ = 'faq';

    public const TYPE_GUIDE = 'guide';

    protected $attributes = [
        'type' => self::TYPE_ARTICLE,
        'is_published' => false,
        'view_count' => 0,
    ];

    protected $fillable = [
        'knowledge_base_category_id',
        'type',
        'title',
        'slug',
        'content',
        'is_published',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function newFactory(): KnowledgeBaseArticleFactory
    {
        return KnowledgeBaseArticleFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseCategory::class, 'knowledge_base_category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }
}
