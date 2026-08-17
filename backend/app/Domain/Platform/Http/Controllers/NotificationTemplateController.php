<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Platform\Http\Requests\NotificationTemplateRequest;
use Illuminate\Http\RedirectResponse;

class NotificationTemplateController extends Controller
{
    public function store(NotificationTemplateRequest $request): RedirectResponse
    {
        NotificationTemplate::query()->create($request->validated());

        return back()->with('status', 'notification-template-created');
    }

    public function update(NotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $notificationTemplate->update($request->validated());

        return back()->with('status', 'notification-template-updated');
    }

    public function destroy(NotificationTemplate $notificationTemplate): RedirectResponse
    {
        if ($notificationTemplate->is_system) {
            return back()->withErrors(['notification_template' => 'System templates cannot be deleted.']);
        }

        $notificationTemplate->delete();

        return back()->with('status', 'notification-template-deleted');
    }
}
