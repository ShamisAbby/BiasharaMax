<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class NotificationController extends Controller
{
    /**
     * Return notifications as JSON for XHR (notification bell dropdown)
     * or as an Inertia page if navigated to directly.
     */
    public function index(Request $request): JsonResponse|Response|\Inertia\Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($notification) => [
                'id'         => $notification->id,
                'title'      => $notification->data['title'] ?? '',
                'message'    => $notification->data['message'] ?? '',
                'url'        => $notification->data['url'] ?? null,
                'icon'       => $notification->data['icon'] ?? null,
                'read_at'    => $notification->read_at,
                'created_at' => $notification->created_at,
            ]);

        $unreadCount = $user->unreadNotifications()->count();

        // XHR from the notification bell component → return JSON
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'notifications' => $notifications,
                'unread_count'  => $unreadCount,
            ]);
        }

        // Direct browser navigation → Inertia page
        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, string $notification): Response
    {
        $request->user()
            ->notifications()
            ->where('id', $notification)
            ->first()
            ?->markAsRead();

        return response()->noContent();
    }

    public function markAllAsRead(Request $request): Response
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }

    /**
     * Clears the notification list.
     *
     * Deletes rather than marks read, because these are real rows in an
     * inbox and "clear" that leaves everything in place is not what
     * anyone means by it. Scoped through `$request->user()->notifications()`,
     * so the relation constrains it to the caller's own — never a
     * `where('id', ...)` against the table, which would let one user
     * clear another's.
     *
     * Unlike the platform feed, this is genuinely permanent: these are
     * records of things that happened, not conditions that are still
     * true, so nothing regenerates them.
     */
    public function clear(Request $request): Response
    {
        $request->user()->notifications()->delete();

        return response()->noContent();
    }

    public function destroy(Request $request, string $notification): Response
    {
        $request->user()
            ->notifications()
            ->where('id', $notification)
            ->delete();

        return response()->noContent();
    }
}
