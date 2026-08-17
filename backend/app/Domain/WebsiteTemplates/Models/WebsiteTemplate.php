<?php

namespace App\Domain\WebsiteTemplates\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Database\Factories\WebsiteTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebsiteTemplate extends Model
{
    use Auditable, HasFactory, HasUserstamps, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const PAGE_TYPES = [
        'homepage', 'about', 'products', 'categories', 'services', 'gallery',
        'testimonials', 'blog', 'contact', 'faq', 'privacy_policy', 'terms', 'booking_form',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'version' => '1.0.0',
        'is_default' => false,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'name',
        'slug',
        'business_type_id',
        'description',
        'thumbnail_path',
        'preview_url',
        'status',
        'version',
        'is_default',
        'theme_colors',
        'typography',
        'custom_css',
        'header_config',
        'footer_config',
        'navigation_config',
        'seo_settings',
        'social_media',
        'whatsapp_number',
        'google_maps_embed',
        'analytics_code',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'theme_colors' => 'array',
            'typography' => 'array',
            'header_config' => 'array',
            'footer_config' => 'array',
            'navigation_config' => 'array',
            'seo_settings' => 'array',
            'social_media' => 'array',
        ];
    }

    protected static function newFactory(): WebsiteTemplateFactory
    {
        return WebsiteTemplateFactory::new();
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(WebsiteTemplatePage::class)->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WebsiteTemplateVersion::class)->latest('created_at');
    }

    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'website_template_subscription_plan');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }
}
