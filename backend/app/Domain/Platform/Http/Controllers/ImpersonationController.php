<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Business;
use App\Domain\Platform\Models\ImpersonationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets a SuperAdmin open a business owner's account for support purposes.
 * Every session is logged in `impersonation_logs` with who, whom, which
 * business and when — this is a high-trust capability, so the audit
 * trail is not optional.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, Business $business): RedirectResponse
    {
        $owner = $business->owner;

        abort_if($owner === null, 404);

        $platformUser = Auth::guard('platform')->user();

        $log = ImpersonationLog::query()->create([
            'platform_user_id' => $platformUser->id,
            'user_id' => $owner->id,
            'business_id' => $business->id,
            'ip_address' => $request->ip(),
            'started_at' => now(),
        ]);

        $request->session()->put('impersonation_log_id', $log->id);

        Auth::guard('web')->login($owner);

        return redirect()->route('dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $logId = $request->session()->get('impersonation_log_id');

        if ($logId) {
            ImpersonationLog::query()->whereKey($logId)->update(['ended_at' => now()]);
            $request->session()->forget('impersonation_log_id');
        }

        Auth::guard('web')->logout();

        return redirect()->route('platform.dashboard');
    }
}
