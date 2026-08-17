<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Notifications\Models\NotificationChannel;
use App\Domain\Platform\Http\Requests\NotificationChannelRequest;
use App\Domain\Platform\Http\Resources\NotificationChannelResource;
use App\Domain\Platform\Http\Resources\NotificationCampaignResource;
use App\Domain\Platform\Http\Resources\NotificationTemplateResource;
use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Notifications\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationChannelController extends Controller
{
    public function index(Request $request): Response
    {
        $channels = NotificationChannel::query()->orderBy('sort_order')->get();

        return Inertia::render('Platform/Operations/Notifications/Index', [
            'channels' => NotificationChannelResource::collection($channels),
            'templates' => NotificationTemplateResource::collection(NotificationTemplate::query()->orderBy('name')->get()),
            'campaigns' => NotificationCampaignResource::collection(NotificationCampaign::query()->latest('created_at')->limit(50)->get()),

            /*
             * Which channel types can actually deliver right now.
             *
             * Derived from the channels already loaded above, so it costs
             * nothing, and it is what lets a failed campaign explain
             * itself on this page: the overwhelmingly common cause is
             * that no channel of that type is enabled. The alternative —
             * reading each campaign's delivery rows for an error message
             * — is an N+1 across fifty campaigns to display one sentence.
             */
            'enabledChannels' => $channels
                ->where('is_enabled', true)
                ->pluck('channel')
                ->unique()
                ->values(),

            /** Every channel type the platform supports, for the create form. */
            'channelTypes' => [
                NotificationChannel::CHANNEL_IN_APP => 'In-app',
                NotificationChannel::CHANNEL_EMAIL => 'Email',
                NotificationChannel::CHANNEL_SMS => 'SMS',
                NotificationChannel::CHANNEL_WHATSAPP => 'WhatsApp',
                NotificationChannel::CHANNEL_PUSH => 'Push',
            ],

            /*
             * The real categories from NotificationTemplate, sent rather
             * than hardcoded in the form.
             *
             * The create form briefly offered "transactional / marketing /
             * system", none of which are values this model defines — a
             * template saved that way would have been filed under a
             * category nothing else in the platform recognises.
             */
            'templateCategories' => [
                NotificationTemplate::CATEGORY_BROADCAST => 'Broadcast',
                NotificationTemplate::CATEGORY_MARKETING => 'Marketing',
                NotificationTemplate::CATEGORY_SYSTEM_ALERT => 'System alert',
                NotificationTemplate::CATEGORY_SUBSCRIPTION_REMINDER => 'Subscription reminder',
                NotificationTemplate::CATEGORY_LICENSE_EXPIRY => 'Licence expiry',
                NotificationTemplate::CATEGORY_PAYMENT_SUCCESS => 'Payment success',
                NotificationTemplate::CATEGORY_PAYMENT_FAILURE => 'Payment failure',
                NotificationTemplate::CATEGORY_LOW_STOCK => 'Low stock',
                NotificationTemplate::CATEGORY_SUPPORT_TICKET_UPDATE => 'Support ticket update',
                NotificationTemplate::CATEGORY_BUSINESS_APPROVAL => 'Business approval',
                NotificationTemplate::CATEGORY_USER_REGISTRATION => 'User registration',
                NotificationTemplate::CATEGORY_CUSTOM => 'Custom',
            ],
        ]);
    }

    public function store(NotificationChannelRequest $request): RedirectResponse
    {
        NotificationChannel::query()->create($request->validated());

        return back()->with('status', 'channel-created');
    }

    public function update(NotificationChannelRequest $request, NotificationChannel $notificationChannel): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('credentials', $data)) {
            $data['credentials'] = array_filter($data['credentials'] ?? [], fn ($v) => $v !== '' && $v !== null);
            $data['credentials'] = $data['credentials'] === []
                ? $notificationChannel->credentials
                : array_merge($notificationChannel->credentials ?? [], $data['credentials']);
        }

        $notificationChannel->update($data);

        return back()->with('status', 'channel-updated');
    }

    public function enable(NotificationChannel $notificationChannel): RedirectResponse
    {
        $notificationChannel->update(['is_enabled' => true]);

        return back()->with('status', 'channel-enabled');
    }

    public function disable(NotificationChannel $notificationChannel): RedirectResponse
    {
        $notificationChannel->update(['is_enabled' => false]);

        return back()->with('status', 'channel-disabled');
    }
}
