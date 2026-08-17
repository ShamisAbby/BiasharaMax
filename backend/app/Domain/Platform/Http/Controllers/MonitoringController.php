<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Monitoring\Models\BackupRecord;
use App\Domain\Monitoring\Models\SystemHealthSnapshot;
use App\Domain\Monitoring\Services\SystemMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    public function index(Request $request, SystemMetricsService $metrics): Response
    {
        $trend = SystemHealthSnapshot::query()
            ->where('recorded_at', '>=', now()->subHours(24))
            ->orderBy('recorded_at')
            ->get(['cpu_usage', 'memory_usage', 'disk_usage', 'health_score', 'recorded_at']);

        $backups = BackupRecord::query()->latest('started_at')->limit(20)->get();

        return Inertia::render('Platform/Operations/Monitoring/Index', [
            'live' => $metrics->currentSnapshot(),
            'trend' => $trend,
            'backups' => $backups,
        ]);
    }
}
