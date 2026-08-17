<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Integrations\Services\IntegrationService;
use App\Domain\Platform\Http\Requests\IntegrationRequest;
use App\Domain\Platform\Http\Resources\IntegrationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $integrations = Integration::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Platform/System/Integrations/Index', [
            'integrations' => IntegrationResource::collection($integrations),
            'categories' => Integration::query()->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only(['category']),
        ]);
    }

    public function store(IntegrationRequest $request, IntegrationService $service): RedirectResponse
    {
        $service->create($request->validated());

        return back()->with('status', 'integration-created');
    }

    public function update(IntegrationRequest $request, Integration $integration, IntegrationService $service): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('credentials', $data)) {
            $data['credentials'] = array_filter($data['credentials'] ?? [], fn ($v) => $v !== '' && $v !== null);
            $data['credentials'] = $data['credentials'] === []
                ? $integration->credentials
                : array_merge($integration->credentials ?? [], $data['credentials']);
        }

        $service->update($integration, $data);

        return back()->with('status', 'integration-updated');
    }

    public function enable(Integration $integration, IntegrationService $service): RedirectResponse
    {
        $service->enable($integration);

        return back()->with('status', 'integration-enabled');
    }

    public function disable(Integration $integration, IntegrationService $service): RedirectResponse
    {
        $service->disable($integration);

        return back()->with('status', 'integration-disabled');
    }

    public function testConnection(Integration $integration, IntegrationService $service): RedirectResponse
    {
        $log = $service->testConnection($integration);

        return back()->with('status', $log->is_successful ? 'connection-successful' : 'connection-failed');
    }

    public function logs(Integration $integration): Response
    {
        $logs = $integration->logs()->paginate(25)->withQueryString();

        $logs->through(fn (IntegrationLog $log) => [
            'id' => $log->id,
            'direction' => $log->direction,
            'event_type' => $log->event_type,
            'status_code' => $log->status_code,
            'is_successful' => $log->is_successful,
            'error_message' => $log->error_message,
            'response_payload' => $log->response_payload,
            'created_at' => $log->created_at,
        ]);

        return Inertia::render('Platform/System/Integrations/Logs', [
            'integration' => new IntegrationResource($integration),
            'logs' => [
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'total' => $logs->total(),
                    'links' => $logs->linkCollection()->toArray(),
                ],
            ],
        ]);
    }
}
