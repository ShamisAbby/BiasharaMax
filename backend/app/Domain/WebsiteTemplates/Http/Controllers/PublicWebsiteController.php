<?php

namespace App\Domain\WebsiteTemplates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Business;
use App\Domain\Inventory\Models\Product;
use App\Domain\Website\Models\Article;
use App\Domain\Website\Models\BusinessWebsite;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, unauthenticated storefront for a tenant business — renders the
 * website template assigned to the business's BusinessType, unless the
 * business has its own published BusinessWebsite override (real owner
 * edits), in which case those pages and SEO fields take precedence over
 * the shared template's.
 */
class PublicWebsiteController extends Controller
{
    /**
     * Used when a business has published its own pages but its business
     * type has no shared template to take a palette from. Neutral on
     * purpose — it should read as unstyled-but-clean rather than
     * impersonating a design the owner never chose. All seven keys are
     * required: the renderer maps each to a `--brand-*` custom property.
     */
    private const DEFAULT_THEME_COLORS = [
        'primary' => '#4F46E5',
        'secondary' => '#312E81',
        'accent' => '#F59E0B',
        'background' => '#FFFFFF',
        'surface' => '#F8FAFC',
        'text' => '#0F172A',
        'muted' => '#64748B',
    ];

    private const DEFAULT_TYPOGRAPHY = [
        'heading_font' => 'Inter',
        'body_font' => 'Inter',
    ];

    public function show(Business $business): Response
    {
        $business->loadMissing([
            'businessType.websiteTemplate' => fn ($query) => $query->where('status', 'published'),
            'businessType.websiteTemplate.pages' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order'),
        ]);

        $template = $business->businessType?->websiteTemplate;

        $site = BusinessWebsite::query()
            ->where('business_id', $business->id)
            ->with(['pages' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')])
            ->first();

        // The withdrawn case (published once, since taken down) never
        // reaches here — EnsureBusinessWebsiteIsNotWithdrawn intercepts it
        // for this route and every other page under `site/{business}`.
        // A site that has NEVER been published still falls through to the
        // business type's template below, so a new business is never a
        // blank page on day one.
        $ownWebsite = $site?->status === BusinessWebsite::STATUS_PUBLISHED ? $site : null;

        $toPagePayload = fn ($page) => [
            'type' => $page->type,
            'title' => $page->title,
            'slug' => $page->slug,
            'content' => $page->content,
        ];

        $ownPages = $ownWebsite?->pages->map($toPagePayload) ?? collect();
        $templatePages = $template?->pages->map($toPagePayload) ?? collect();

        // The owner's own pages take precedence ONLY when they actually
        // have some. Switching on whether the BusinessWebsite row merely
        // exists made publishing an empty site wipe the page list: the
        // owner saw a bare header and footer while published, and the
        // full template site while unpublished — publish and unpublish
        // appearing to be the wrong way round.
        //
        // Falling back keeps a published-but-empty site showing the
        // business type's template, which is what an owner who hasn't
        // written any pages yet expects to see.
        $pages = $ownPages->isNotEmpty() ? $ownPages : $templatePages;

        $seoSettings = $template?->seo_settings ?? [];
        if ($ownWebsite?->seo_title || $ownWebsite?->seo_description) {
            $seoSettings = [
                'title_suffix' => $ownWebsite->seo_title ?? $seoSettings['title_suffix'] ?? null,
                'meta_description' => $ownWebsite->seo_description ?? $seoSettings['meta_description'] ?? null,
            ];
        }

        return Inertia::render('PublicWebsite/Show', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'logo_path' => $business->logo_path,
                'email' => $business->email,
                'phone' => $business->phone,
                'address' => $business->address,
                'city' => $business->city,
                'has_shop' => Product::query()
                    ->where('business_id', $business->id)
                    ->where('status', Product::STATUS_ACTIVE)
                    ->where('visibility', Product::VISIBILITY_VISIBLE)
                    ->exists(),
                'has_blog' => Article::query()
                    ->where('business_id', $business->id)
                    ->published()
                    ->exists(),
            ],
            // Keyed off whether there is anything to SHOW, not off whether
            // the business TYPE happens to have a shared template. A
            // business that published its own site with its own pages was
            // previously still sent `null` here — the pages gathered above
            // were built and then thrown away — so the storefront rendered
            // "hasn't published a website yet" despite being published.
            //
            // Presentation still comes from the shared template when there
            // is one; otherwise the neutral defaults below keep the page
            // renderable. Every key the renderer reads must be present:
            // it destructures theme_colors and typography directly.
            'template' => ($template || $pages->isNotEmpty()) ? [
                'name' => $template?->name ?? $business->name,
                'theme_colors' => $template?->theme_colors ?? self::DEFAULT_THEME_COLORS,
                'typography' => $template?->typography ?? self::DEFAULT_TYPOGRAPHY,
                'header_config' => $template?->header_config,
                'footer_config' => $template?->footer_config,
                'seo_settings' => $seoSettings,
                'social_media' => $template?->social_media,
                'whatsapp_number' => $template?->whatsapp_number,
                'google_maps_embed' => $template?->google_maps_embed,
                'pages' => $pages,
            ] : null,
        ]);
    }
}
