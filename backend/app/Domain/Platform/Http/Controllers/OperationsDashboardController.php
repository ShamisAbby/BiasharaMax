<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Monitoring\Services\SystemMetricsService;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Security\Models\FailedLoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Shared\Models\AuditLog;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OperationsDashboardController extends Controller
{
    public function __invoke(Request $request, SystemMetricsService $metrics): Response
    {
        $live = $metrics->currentSnapshot();

        return Inertia::render('Platform/Operations/Dashboard', [
            'websiteTemplates' => [
                'total' => WebsiteTemplate::query()->count(),
                'published' => WebsiteTemplate::query()->where('status', WebsiteTemplate::STATUS_PUBLISHED)->count(),
            ],
            'notificationsSentToday' => NotificationDelivery::query()
                ->whereIn('status', [NotificationDelivery::STATUS_SENT, NotificationDelivery::STATUS_DELIVERED])
                ->whereDate('created_at', today())
                ->count(),
            'openSupportTickets' => SupportTicket::query()->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS, SupportTicket::STATUS_PENDING])->count(),
            'criticalSecurityAlerts' => SecurityAlert::query()->where('severity', SecurityAlert::SEVERITY_CRITICAL)->where('is_resolved', false)->count(),
            'failedLogins24h' => FailedLoginAttempt::query()->where('created_at', '>=', now()->subDay())->count(),
            'activeSessions' => DB::table('sessions')->where('last_activity', '>=', now()->subMinutes(30)->getTimestamp())->count(),
            'serverHealth' => [
                'cpu_usage' => $live['cpu_usage'],
                'memory_usage' => $live['memory_usage'],
                'disk_usage' => $live['disk_usage'],
                'health_score' => $live['health_score'],
            ],
            'apiRequestsToday' => null,
            'platformUptime' => $live['uptime'],
            'recentActivities' => AuditLog::query()
                ->with('business')
                ->latest('created_at')
                ->limit(15)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'module' => $log->module,
                    'action' => $log->action,
                    'auditable_type' => $log->auditable_type ? class_basename($log->auditable_type) : null,
                    'business_name' => $log->business?->name,
                    'risk_level' => $log->risk_level,
                    'created_at' => $log->created_at,
                ]),
        ]);
    }
}
