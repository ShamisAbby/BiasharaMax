<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebsiteTemplate
 */
class WebsiteTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'business_type_id' => $this->business_type_id,
            'business_type_name' => $this->whenLoaded('businessType', fn () => $this->businessType?->name),
            'description' => $this->description,
            'thumbnail_path' => $this->thumbnail_path,
            'preview_url' => $this->preview_url,
            'status' => $this->status,
            'version' => $this->version,
            'is_default' => $this->is_default,
            'theme_colors' => $this->theme_colors,
            'typography' => $this->typography,
            'custom_css' => $this->custom_css,
            'header_config' => $this->header_config,
            'footer_config' => $this->footer_config,
            'navigation_config' => $this->navigation_config,
            'seo_settings' => $this->seo_settings,
            'social_media' => $this->social_media,
            'whatsapp_number' => $this->whatsapp_number,
            'google_maps_embed' => $this->google_maps_embed,
            'analytics_code' => $this->analytics_code,
            'sort_order' => $this->sort_order,
            'pages' => $this->whenLoaded('pages', fn () => $this->pages->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'title' => $p->title,
                'slug' => $p->slug,
                'content' => $p->content,
                'is_enabled' => $p->is_enabled,
                'sort_order' => $p->sort_order,
            ])),
            'subscription_plans' => $this->whenLoaded('subscriptionPlans', fn () => $this->subscriptionPlans->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])),
            'versions_count' => $this->whenCounted('versions'),
            'created_at' => $this->created_at,
        ];
    }
}
