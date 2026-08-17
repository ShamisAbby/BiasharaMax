<?php

namespace App\Domain\WebsiteTemplates\Services;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebsiteTemplateService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WebsiteTemplate
    {
        return WebsiteTemplate::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WebsiteTemplate $template, array $data): WebsiteTemplate
    {
        $template->update($data);

        return $template->refresh();
    }

    public function clone(WebsiteTemplate $template, string $newName): WebsiteTemplate
    {
        return DB::transaction(function () use ($template, $newName) {
            $clone = $template->replicate(['slug', 'is_default']);
            $clone->name = $newName;
            $clone->slug = Str::slug($newName).'-'.Str::lower(Str::random(4));
            $clone->status = WebsiteTemplate::STATUS_DRAFT;
            $clone->is_default = false;
            $clone->save();

            foreach ($template->pages as $page) {
                $clone->pages()->create([
                    'type' => $page->type,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'content' => $page->content,
                    'is_enabled' => $page->is_enabled,
                    'sort_order' => $page->sort_order,
                ]);
            }

            return $clone;
        });
    }

    /**
     * Publishing snapshots the template + its pages for rollback/version
     * comparison, then flips status to published.
     */
    public function publish(WebsiteTemplate $template, ?PlatformUser $actor = null): WebsiteTemplate
    {
        return DB::transaction(function () use ($template, $actor) {
            WebsiteTemplateVersion::query()->create([
                'website_template_id' => $template->id,
                'version' => $template->version,
                'snapshot' => [
                    'template' => $template->only([
                        'name', 'slug', 'theme_colors', 'typography', 'custom_css',
                        'header_config', 'footer_config', 'navigation_config',
                        'seo_settings', 'social_media',
                    ]),
                    'pages' => $template->pages()->get()->map(fn ($p) => $p->only([
                        'type', 'title', 'slug', 'content', 'is_enabled', 'sort_order',
                    ]))->all(),
                ],
                'published_by' => $actor?->id,
            ]);

            $template->update(['status' => WebsiteTemplate::STATUS_PUBLISHED]);

            return $template->refresh();
        });
    }

    public function archive(WebsiteTemplate $template): WebsiteTemplate
    {
        $template->update(['status' => WebsiteTemplate::STATUS_ARCHIVED]);

        return $template->refresh();
    }

    /**
     * @param  array<int, string>  $planIds
     */
    public function assignToPlans(WebsiteTemplate $template, array $planIds): void
    {
        $template->subscriptionPlans()->sync($planIds);
    }
}
