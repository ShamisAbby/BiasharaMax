<?php

namespace App\Domain\Website\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Website\Models\BusinessWebsite;
use App\Domain\Website\Models\BusinessWebsitePage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BusinessWebsiteService
{
    /**
     * Returns the business's own editable website, creating it (seeded
     * from the WebsiteTemplate assigned to its BusinessType) the first
     * time the owner opens the Website dashboard. Never re-seeds an
     * existing website — owner edits are never clobbered.
     */
    public function getOrInitialize(Business $business): BusinessWebsite
    {
        $website = BusinessWebsite::query()->where('business_id', $business->id)->first();

        if ($website) {
            return $website;
        }

        $business->loadMissing('businessType.websiteTemplate.pages');
        $template = $business->businessType?->websiteTemplate;

        return DB::transaction(function () use ($business, $template) {
            $website = BusinessWebsite::create([
                'business_id' => $business->id,
                'website_template_id' => $template?->id,
            ]);

            foreach ($template?->pages ?? [] as $index => $page) {
                $website->pages()->create([
                    'type' => $page->type,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'content' => $page->content,
                    'is_enabled' => $page->is_enabled,
                    'sort_order' => $index,
                ]);
            }

            return $website;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(BusinessWebsite $website, array $data): void
    {
        $website->update($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePage(BusinessWebsitePage $page, array $data): void
    {
        $page->update($data);
    }

    public function publish(BusinessWebsite $website): void
    {
        $website->update([
            'status' => BusinessWebsite::STATUS_PUBLISHED,
            'published_at' => Carbon::now(),
        ]);
    }

    public function unpublish(BusinessWebsite $website): void
    {
        $website->update(['status' => BusinessWebsite::STATUS_DRAFT]);
    }
}
