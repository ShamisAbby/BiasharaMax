<?php

namespace App\Domain\Monitoring\Console\Commands;

use App\Domain\Monitoring\Models\SystemHealthSnapshot;
use App\Domain\Monitoring\Services\SystemMetricsService;
use Illuminate\Console\Command;

/**
 * Runs every 5 minutes (see routes/console.php) to populate the
 * historical trend charts on the System Monitoring dashboard. Live
 * widgets read the OS/Horizon/Redis directly and don't need this.
 */
class RecordSystemHealthSnapshot extends Command
{
    protected $signature = 'monitoring:snapshot';

    protected $description = 'Record a point-in-time system health snapshot for trend charts.';

    public function handle(SystemMetricsService $metrics): int
    {
        $data = $metrics->currentSnapshot();

        SystemHealthSnapshot::query()->create([
            'cpu_usage' => $data['cpu_usage'],
            'memory_usage' => $data['memory_usage'],
            'disk_usage' => $data['disk_usage'],
            'queue_pending' => $data['queue_pending'],
            'queue_failed' => $data['queue_failed'],
            'db_response_time_ms' => $data['db_response_time_ms'],
            'redis_status' => $data['redis_status'],
            'horizon_status' => $data['horizon_status'],
            'health_score' => $data['health_score'],
            'recorded_at' => now(),
        ]);

        $this->info('System health snapshot recorded.');

        return self::SUCCESS;
    }
}
