<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Developer\Models\Webhook;
use App\Domain\Developer\Services\DeveloperToolsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeveloperCenterController extends Controller
{
    public function index(Request $request, DeveloperToolsService $tools): Response
    {
        return Inertia::render('Platform/Operations/Developer/Index', [
            'systemInfo' => $tools->systemInfo(),
            'queueStatus' => $tools->queueStatus(),
            'routes' => $tools->routeList(),
            'migrations' => $tools->migrationStatus(),
            'webhooks' => Webhook::query()->withCount('deliveries')->get()->map(fn (Webhook $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'url' => $w->url,
                'events' => $w->events,
                'is_active' => $w->is_active,
                'deliveries_count' => $w->deliveries_count,
            ]),
            'apiTokens' => $request->user()->tokens()->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']),
            'plainTextToken' => session('plain_text_token'),
        ]);
    }

    public function clearCache(DeveloperToolsService $tools): \Illuminate\Http\RedirectResponse
    {
        $tools->clearCache();

        return back()->with('status', 'cache-cleared');
    }
}
