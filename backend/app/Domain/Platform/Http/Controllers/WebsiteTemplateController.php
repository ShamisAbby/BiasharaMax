<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Platform\Http\Requests\WebsiteTemplatePageRequest;
use App\Domain\Platform\Http\Requests\WebsiteTemplateRequest;
use App\Domain\Platform\Http\Resources\WebsiteTemplateResource;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplatePage;
use App\Domain\WebsiteTemplates\Services\WebsiteTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = WebsiteTemplate::query()
            ->with(['businessType', 'pages', 'subscriptionPlans'])
            ->withCount('versions')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Platform/Operations/WebsiteTemplates/Index', [
            'templates' => WebsiteTemplateResource::collection($templates),
            'businessTypes' => BusinessType::query()->orderBy('name')->get(['id', 'name']),
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    /**
     * Renders a template as a visitor would see it.
     *
     * Reuses `PublicWebsite/Show` — the same component the live
     * storefront uses — rather than building a separate preview
     * renderer. A preview that draws the page differently from
     * production is worth less than no preview, because the thing being
     * judged is precisely how it will look.
     *
     * Templates belong to no business, so one is invented. The sample
     * content below is deliberately plausible rather than "Lorem ipsum"
     * or "Test Business": an admin judging whether a layout works needs
     * to see it holding something the length and shape of real content.
     *
     * Archived and draft templates are previewable on purpose — checking
     * how a draft looks before publishing it is the main reason to open
     * a preview at all.
     */
    public function preview(WebsiteTemplate $websiteTemplate): Response
    {
        $websiteTemplate->load('pages');

        return Inertia::render('PublicWebsite/Show', [
            'preview' => [
                'templateName' => $websiteTemplate->name,
                'status' => $websiteTemplate->status,
                'backUrl' => route('platform.operations.website-templates.index'),
            ],
            'business' => [
                'name' => $websiteTemplate->name,
                'slug' => $websiteTemplate->slug,
                'logo_path' => null,
                'email' => 'hello@example.com',
                'phone' => '+255 700 000 000',
                'address' => '12 Samora Avenue',
                'city' => 'Dar es Salaam',
                // Both true so every navigation branch the template can
                // render is exercised. A preview that hides the shop link
                // would not show whether the header copes with it.
                'has_shop' => true,
                'has_blog' => true,
            ],
            'template' => [
                'name' => $websiteTemplate->name,
                'theme_colors' => $websiteTemplate->theme_colors ?? [],
                'typography' => $websiteTemplate->typography ?? [],
                'header_config' => $websiteTemplate->header_config,
                'footer_config' => $websiteTemplate->footer_config,
                'seo_settings' => $websiteTemplate->seo_settings ?? [],
                'social_media' => $websiteTemplate->social_media,
                'whatsapp_number' => $websiteTemplate->whatsapp_number,
                'google_maps_embed' => $websiteTemplate->google_maps_embed,
                'pages' => $websiteTemplate->pages->map(fn (WebsiteTemplatePage $page): array => [
                    'type' => $page->type,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'content' => $page->content,
                ]),
            ],
        ]);
    }

    public function store(WebsiteTemplateRequest $request, WebsiteTemplateService $service): RedirectResponse
    {
        $service->create($request->validated());

        return back()->with('status', 'template-created');
    }

    public function update(WebsiteTemplateRequest $request, WebsiteTemplate $websiteTemplate, WebsiteTemplateService $service): RedirectResponse
    {
        $service->update($websiteTemplate, $request->validated());

        return back()->with('status', 'template-updated');
    }

    public function destroy(WebsiteTemplate $websiteTemplate): RedirectResponse
    {
        $websiteTemplate->delete();

        return back()->with('status', 'template-deleted');
    }

    public function publish(WebsiteTemplate $websiteTemplate, WebsiteTemplateService $service): RedirectResponse
    {
        $service->publish($websiteTemplate, request()->user());

        return back()->with('status', 'template-published');
    }

    public function archive(WebsiteTemplate $websiteTemplate, WebsiteTemplateService $service): RedirectResponse
    {
        $service->archive($websiteTemplate);

        return back()->with('status', 'template-archived');
    }

    public function clone(Request $request, WebsiteTemplate $websiteTemplate, WebsiteTemplateService $service): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $service->clone($websiteTemplate, $validated['name']);

        return back()->with('status', 'template-cloned');
    }

    public function assignToPlans(Request $request, WebsiteTemplate $websiteTemplate, WebsiteTemplateService $service): RedirectResponse
    {
        $validated = $request->validate([
            'plan_ids' => ['array'],
            'plan_ids.*' => ['uuid', 'exists:subscription_plans,id'],
        ]);

        $service->assignToPlans($websiteTemplate, $validated['plan_ids'] ?? []);

        return back()->with('status', 'template-plans-updated');
    }

    public function storePage(WebsiteTemplatePageRequest $request, WebsiteTemplate $websiteTemplate): RedirectResponse
    {
        $websiteTemplate->pages()->create($request->validated());

        return back()->with('status', 'page-created');
    }

    public function updatePage(WebsiteTemplatePageRequest $request, WebsiteTemplate $websiteTemplate, WebsiteTemplatePage $page): RedirectResponse
    {
        abort_unless($page->website_template_id === $websiteTemplate->id, 404);

        $page->update($request->validated());

        return back()->with('status', 'page-updated');
    }

    public function destroyPage(WebsiteTemplate $websiteTemplate, WebsiteTemplatePage $page): RedirectResponse
    {
        abort_unless($page->website_template_id === $websiteTemplate->id, 404);

        $page->delete();

        return back()->with('status', 'page-deleted');
    }
}
