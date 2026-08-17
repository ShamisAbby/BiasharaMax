<?php

namespace App\Domain\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Website\Http\Requests\BusinessWebsitePageUpdateRequest;
use App\Domain\Website\Http\Requests\BusinessWebsiteSettingsRequest;
use App\Domain\Website\Http\Resources\BusinessWebsiteResource;
use App\Domain\Website\Models\BusinessWebsite;
use App\Domain\Website\Models\BusinessWebsitePage;
use App\Domain\Website\Services\BusinessWebsiteService;
use App\Domain\Website\Services\WebsiteDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessWebsiteController extends Controller
{
    public function __construct(
        private readonly BusinessWebsiteService $websiteService,
    ) {}

    public function show(Request $request, WebsiteDashboardService $dashboardService): Response
    {
        abort_unless($request->user()->hasPermission('website.view'), 403);

        $businessId = $request->user()->business_id;
        $website = $this->websiteService->getOrInitialize($request->user()->business);
        $website->load(['template', 'pages']);

        return Inertia::render('Website/Dashboard', [
            'website' => new BusinessWebsiteResource($website),
            'subdomainUrl' => route('public.website.show', $request->user()->business->slug),
            'summary' => $dashboardService->summary($businessId),
            'recentOrders' => $dashboardService->recentOrders($businessId),
        ]);
    }

    public function pages(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('website.view'), 403);

        $website = $this->websiteService->getOrInitialize($request->user()->business);
        $website->load(['pages']);

        return Inertia::render('Website/Pages', [
            'website' => new BusinessWebsiteResource($website),
        ]);
    }

    public function updateSettings(BusinessWebsiteSettingsRequest $request, BusinessWebsite $website): RedirectResponse
    {
        $this->websiteService->updateSettings($website, $request->validated());

        return back()->with('status', 'website-settings-updated');
    }

    public function updatePage(BusinessWebsitePageUpdateRequest $request, BusinessWebsite $website, BusinessWebsitePage $page): RedirectResponse
    {
        abort_unless($page->business_website_id === $website->id, 404);

        $this->websiteService->updatePage($page, $request->validated());

        return back()->with('status', 'website-page-updated');
    }

    public function publish(Request $request, BusinessWebsite $website): RedirectResponse
    {
        $this->authorize('update', $website);
        $this->websiteService->publish($website);

        return back()->with('status', 'website-published');
    }

    public function unpublish(Request $request, BusinessWebsite $website): RedirectResponse
    {
        $this->authorize('update', $website);
        $this->websiteService->unpublish($website);

        return back()->with('status', 'website-unpublished');
    }
}
