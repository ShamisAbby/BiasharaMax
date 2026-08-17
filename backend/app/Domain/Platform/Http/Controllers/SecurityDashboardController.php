<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Models\BlockedIp;
use App\Domain\Security\Models\FailedLoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Services\ActiveSessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityDashboardController extends Controller
{
    public function index(Request $request, ActiveSessionService $sessions): Response
    {
        $failedLogins = FailedLoginAttempt::query()->latest('created_at')->limit(50)->get();
        $blockedIps = BlockedIp::query()->latest('created_at')->get();
        $lockouts = AccountLockout::query()->latest('locked_at')->limit(50)->get();
        $alerts = SecurityAlert::query()->latest('created_at')->limit(100)->get();

        // Through the shared service so this screen and the Filament
        // Security Centre agree — including on whether the question is
        // answerable at all. This used to query `sessions` directly and
        // report 0 under a Redis session driver, which reads as "nobody
        // is signed in" rather than "this driver cannot be queried".
        $activeSessions = $sessions->recent();

        return Inertia::render('Platform/Operations/Security/Index', [
            'failedLogins' => $failedLogins,
            'blockedIps' => $blockedIps->map(fn (BlockedIp $ip) => [
                'id' => $ip->id, 'ip_address' => $ip->ip_address, 'reason' => $ip->reason,
                'is_permanent' => $ip->is_permanent, 'expires_at' => $ip->expires_at, 'is_active' => $ip->isActive(),
            ]),
            'lockouts' => $lockouts->map(fn (AccountLockout $l) => [
                'id' => $l->id, 'lockable_type' => $l->lockable_type, 'lockable_id' => $l->lockable_id,
                'reason' => $l->reason, 'locked_at' => $l->locked_at, 'is_active' => $l->isActive(),
            ]),
            'alerts' => $alerts->map(fn (SecurityAlert $a) => [
                'id' => $a->id, 'type' => $a->type, 'severity' => $a->severity, 'description' => $a->description,
                'is_resolved' => $a->is_resolved, 'created_at' => $a->created_at,
            ]),
            'activeSessions' => $activeSessions,
            // So the page can say "not tracked" instead of implying the
            // platform is empty. See ActiveSessionService.
            'sessionsTracked' => $sessions->areTracked(),
            'sessionDriver' => $sessions->driver(),
            'summary' => [
                'failed_logins_24h' => FailedLoginAttempt::query()->where('created_at', '>=', now()->subDay())->count(),
                'blocked_ips_count' => $blockedIps->filter(fn (BlockedIp $ip) => $ip->isActive())->count(),
                'active_lockouts_count' => $lockouts->filter(fn (AccountLockout $l) => $l->isActive())->count(),
                'unresolved_alerts_count' => $alerts->where('is_resolved', false)->count(),
                'active_sessions_count' => $activeSessions->count(),
            ],
        ]);
    }
}
