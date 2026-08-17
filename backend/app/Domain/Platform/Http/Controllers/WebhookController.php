<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Developer\Models\Webhook;
use App\Domain\Developer\Models\WebhookDelivery;
use App\Domain\Developer\Services\WebhookDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
        ]);

        Webhook::query()->create([...$validated, 'secret' => Str::random(40), 'created_by' => $request->user()->id]);

        return back()->with('status', 'webhook-created');
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $webhook->update($validated);

        return back()->with('status', 'webhook-updated');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $webhook->delete();

        return back()->with('status', 'webhook-deleted');
    }

    public function deliveries(Webhook $webhook): Response
    {
        $deliveries = $webhook->deliveries()->paginate(25)->withQueryString();

        return Inertia::render('Platform/Operations/Developer/WebhookDeliveries', [
            'webhook' => ['id' => $webhook->id, 'name' => $webhook->name],
            'deliveries' => [
                'data' => $deliveries->items(),
                'meta' => [
                    'current_page' => $deliveries->currentPage(),
                    'last_page' => $deliveries->lastPage(),
                    'total' => $deliveries->total(),
                    'links' => $deliveries->linkCollection()->toArray(),
                ],
            ],
        ]);
    }

    public function retryDelivery(WebhookDelivery $webhookDelivery, WebhookDispatchService $service): RedirectResponse
    {
        $service->retry($webhookDelivery);

        return back()->with('status', 'delivery-retried');
    }
}
