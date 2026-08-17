<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Platform\Services\PlatformNotificationService;
use App\Domain\Platform\Services\PlatformNotificationStateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformNotificationController extends Controller
{
    public function __invoke(PlatformNotificationService $service): JsonResponse
    {
        $items = $service->current();

        return response()->json([
            'items' => $items,
            'count' => count($items),
        ]);
    }

    /**
     * Dismisses a single item.
     *
     * The key is validated against the live feed rather than trusted:
     * it arrives from the browser, and writing an arbitrary string into
     * the state table would let anyone silence an alert that has not
     * happened yet by guessing its key.
     */
    public function dismiss(
        Request $request,
        PlatformNotificationService $service,
        PlatformNotificationStateService $state,
    ): JsonResponse {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
        ]);

        $items = $service->all();

        abort_unless(
            in_array($validated['key'], collect($items)->pluck('id')->all(), true),
            404,
        );

        /*
         * Sync before dismissing, not after.
         *
         * State rows are created by sync(), which otherwise only runs
         * from the scheduled dispatcher. An admin who dismisses before
         * that first run would be updating a row that does not exist —
         * the request would succeed, return 1, and change nothing, and
         * the item would still be there on the next poll.
         */
        $state->sync($items);
        $state->dismiss($validated['key'], Auth::guard('platform')->id());

        return response()->json(['dismissed' => 1]);
    }

    /**
     * Dismisses everything the operator can currently see.
     *
     * Scoped to `current()` — what they were actually shown — not the
     * whole feed. An item that appeared between the page loading and
     * Clear being pressed is left alone rather than silently marked
     * seen by someone who never saw it.
     */
    public function dismissAll(
        PlatformNotificationService $service,
        PlatformNotificationStateService $state,
    ): JsonResponse {
        // Sync against the full feed so rows exist to dismiss (see the
        // note in dismiss()), then act only on what was actually visible.
        $state->sync($service->all());

        $keys = collect($service->current())->pluck('id')->all();

        return response()->json([
            'dismissed' => $state->dismissAll($keys, Auth::guard('platform')->id()),
        ]);
    }
}
