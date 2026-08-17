<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\AiInsights\Models\AiInsight;
use App\Domain\AiInsights\Services\AiNarrativeService;
use App\Domain\AiInsights\Services\InsightGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiInsightController extends Controller
{
    public function index(Request $request, InsightGenerationService $insights): Response
    {
        return Inertia::render('Platform/System/AiInsights/Index', [
            'revenueForecast' => $insights->revenueForecast(),
            'subscriptionForecast' => $insights->subscriptionForecast(),
            'churnRisk' => $insights->churnRisk(),
            'businessHealthScores' => $insights->businessHealthScores(),
            'growthTrend' => $insights->growthTrend(),
            'mostActiveBusinesses' => $insights->mostActiveBusinesses(),
            'inactiveBusinesses' => $insights->inactiveBusinesses(),
            'revenueByBusinessType' => $insights->revenueByBusinessType(),
            'revenueByCountry' => $insights->revenueByCountry(),
            'savedInsights' => AiInsight::query()->latest('created_at')->limit(20)->get(),
            'aiConfigured' => \App\Domain\Integrations\Models\Integration::query()
                ->where('category', \App\Domain\Integrations\Models\Integration::CATEGORY_AI)
                ->where('is_enabled', true)
                ->exists(),
        ]);
    }

    public function generateNarrative(Request $request, InsightGenerationService $insights, AiNarrativeService $narrative): RedirectResponse
    {
        $validated = $request->validate(['type' => ['required', 'string']]);

        $data = match ($validated['type']) {
            AiInsight::TYPE_REVENUE_FORECAST => $insights->revenueForecast(),
            AiInsight::TYPE_CHURN_RISK => $insights->churnRisk(),
            AiInsight::TYPE_GROWTH_TREND => $insights->growthTrend(),
            default => [],
        };

        $summary = $narrative->summarize($validated['type'], $data);

        if ($summary === null) {
            // The service's own reason, not a guess. It distinguishes
            // "nothing enabled" from "no credentials", "provider name
            // does not map to a driver" and "the provider returned an
            // error" — all of which used to report as the first.
            return back()->withErrors([
                'ai' => $narrative->lastError()
                    ?? 'The AI provider did not return a summary.',
            ]);
        }

        AiInsight::query()->create([
            'type' => $validated['type'],
            'title' => ucwords(str_replace('_', ' ', $validated['type'])),
            'summary' => $summary,
            'data' => $data,
            'generated_by' => AiInsight::GENERATED_BY_AI_PROVIDER,
        ]);

        return back()->with('status', 'narrative-generated');
    }

    public function markRead(AiInsight $aiInsight): RedirectResponse
    {
        $aiInsight->update(['is_read' => true]);

        return back()->with('status', 'insight-marked-read');
    }
}
