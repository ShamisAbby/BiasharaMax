<?php

namespace App\Http\Controllers\Api;

use App\Domain\Subscription\Services\DesktopEntitlementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "May I come in, and if not, what would fix it?"
 *
 * The desktop app calls this after every sign-in and on each launch, and
 * routes on the answer. Keeping the decision here rather than in the
 * Flutter client means a revoked licence or a lapsed trial takes effect
 * on the next launch instead of on the next release, and a modified
 * client cannot let itself in by flipping a local boolean.
 */
class EntitlementController extends Controller
{
    public function __construct(
        private readonly DesktopEntitlementService $entitlements,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('business.subscription.plan');

        return response()->json([
            'entitlement' => $this->entitlements->describe(
                $user->business,
                // Optional: sent by the desktop app so device licensing
                // can be judged for *this* machine. Absent from any other
                // caller, which the service reads as "not activated"
                // rather than waving it through.
                $request->string('device_fingerprint')->value() ?: null,
            ),
        ]);
    }
}
