<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\AiInsights\Models\AiInsight;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Monitoring\Models\BackupRecord;
use App\Domain\Monitoring\Services\SystemMetricsService;
use App\Domain\Notifications\Models\NotificationChannel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemDashboardController extends Controller
{
    public function __invoke(Request $request, SystemMetricsService $metrics): Response
    {
        $live = $metrics->currentSnapshot();
        $latestBackup = BackupRecord::query()->latest('started_at')->first();

        return Inertia::render('Platform/System/Dashboard', [
            'platformVersion' => config('app.version'),
            'laravelVersion' => $live['platform_version'],
            'platformUptime' => $live['uptime'],
            'backupStatus' => $latestBackup ? [
                'status' => $latestBackup->status,
                'started_at' => $latestBackup->started_at,
            ] : null,
            'storageUsage' => $live['disk_usage'],
            'securityStatus' => $live['health_score'] >= 70 ? 'healthy' : 'attention_needed',
            'databaseStatus' => $live['db_response_time_ms'] !== null ? 'online' : 'offline',
            'integrationStatus' => [
                'total' => Integration::query()->count(),
                'enabled' => Integration::query()->where('is_enabled', true)->count(),
            ],
            'emailStatus' => NotificationChannel::query()->where('channel', 'email')->where('is_enabled', true)->exists() ? 'configured' : 'not_configured',
            'smsStatus' => NotificationChannel::query()->where('channel', 'sms')->where('is_enabled', true)->exists() ? 'configured' : 'not_configured',
            'paymentStatus' => PaymentGateway::query()->where('is_enabled', true)->exists() ? 'configured' : 'not_configured',
            'recentBackups' => BackupRecord::query()->latest('started_at')->limit(5)->get(['id', 'type', 'status', 'started_at']),
            'recentIntegrations' => Integration::query()->latest('updated_at')->limit(5)->get(['id', 'name', 'is_enabled', 'last_test_result']),
            'recentAiRecommendations' => AiInsight::query()->latest('created_at')->limit(5)->get(['id', 'title', 'summary', 'created_at']),
        ]);
    }
}
