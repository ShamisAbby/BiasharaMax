<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Services\NotificationDispatchService;
use App\Domain\Platform\Http\Requests\NotificationCampaignRequest;
use App\Domain\Platform\Http\Resources\NotificationCampaignResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCampaignController extends Controller
{
    public function show(NotificationCampaign $notificationCampaign): Response
    {
        $notificationCampaign->load('deliveries');

        return Inertia::render('Platform/Operations/Notifications/CampaignShow', [
            'campaign' => new NotificationCampaignResource($notificationCampaign),
        ]);
    }

    public function store(NotificationCampaignRequest $request): RedirectResponse
    {
        NotificationCampaign::query()->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return back()->with('status', 'campaign-created');
    }

    public function send(NotificationCampaign $notificationCampaign, NotificationDispatchService $service): RedirectResponse
    {
        /*
         * Refused up front rather than allowed to fail per recipient.
         *
         * A campaign with no enabled channel cannot succeed for anybody,
         * so sending it writes one failed delivery per recipient and
         * marks the campaign failed — a lot of rows, and a red badge that
         * does not say the fix is a configuration change. Returning a
         * validation error puts the reason where the administrator is
         * already looking.
         */
        if ($reason = $service->blockedReason($notificationCampaign)) {
            return back()->withErrors(['campaign' => $reason]);
        }

        $campaign = $service->sendCampaign($notificationCampaign);

        return back()->with(
            'status',
            $campaign->failed_count > 0
                ? 'campaign-sent-with-failures'
                : 'campaign-sent',
        );
    }

    public function retryDelivery(NotificationDelivery $notificationDelivery, NotificationDispatchService $service): RedirectResponse
    {
        $service->retry($notificationDelivery);

        return back()->with('status', 'delivery-retried');
    }
}
