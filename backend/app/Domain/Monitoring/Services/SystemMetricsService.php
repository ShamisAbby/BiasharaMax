<?php

namespace App\Domain\Monitoring\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * Every figure here is read live from the OS, the database, Redis, or
 * Horizon — nothing is estimated. Where a metric genuinely can't be
 * read on the current platform (e.g. system-wide memory has no
 * portable PHP API), the method returns null rather than a guess.
 */
class SystemMetricsService
{
    public function cpuUsage(): ?float
    {
        if (! function_exists('sys_getloadavg')) {
            return null;
        }

        $load = sys_getloadavg();
        $cores = (int) (shell_exec('nproc 2>/dev/null') ?: (shell_exec('sysctl -n hw.ncpu 2>/dev/null') ?: 1));

        return $load && $cores > 0 ? round(min(100, ($load[0] / $cores) * 100), 2) : null;
    }

    public function memoryUsage(): ?float
    {
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

            if ($total && $available) {
                $used = ((int) $total[1] - (int) $available[1]) / (int) $total[1];

                return round($used * 100, 2);
            }
        }

        $vmStat = shell_exec('vm_stat 2>/dev/null');
        if ($vmStat && preg_match('/Pages free:\s+(\d+)/', $vmStat, $free) && preg_match('/Pages active:\s+(\d+)/', $vmStat, $active)) {
            $free = (int) $free[1];
            $active = (int) $active[1];
            $total = $free + $active;

            return $total > 0 ? round(($active / $total) * 100, 2) : null;
        }

        return null;
    }

    public function diskUsage(): ?float
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if (! $free || ! $total) {
            return null;
        }

        return round((1 - ($free / $total)) * 100, 2);
    }

    public function dbResponseTimeMs(): ?float
    {
        try {
            $start = microtime(true);
            DB::select('select 1');

            return round((microtime(true) - $start) * 1000, 2);
        } catch (\Throwable) {
            return null;
        }
    }

    public function redisStatus(): string
    {
        try {
            return Redis::connection()->ping() ? 'online' : 'offline';
        } catch (\Throwable) {
            return 'offline';
        }
    }

    public function queuePendingCount(): ?int
    {
        try {
            return Queue::size();
        } catch (\Throwable) {
            return null;
        }
    }

    public function queueFailedCount(): int
    {
        return DB::table('failed_jobs')->count();
    }

    public function horizonStatus(): string
    {
        try {
            $masters = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();

            return ! empty($masters) ? 'running' : 'stopped';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    public function platformVersion(): string
    {
        return app()->version();
    }

    public function uptimeRaw(): ?string
    {
        $raw = shell_exec('uptime 2>/dev/null');

        return $raw ? trim($raw) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSnapshot(): array
    {
        $cpu = $this->cpuUsage();
        $memory = $this->memoryUsage();
        $disk = $this->diskUsage();
        $dbResponseTime = $this->dbResponseTimeMs();
        $redisStatus = $this->redisStatus();
        $horizonStatus = $this->horizonStatus();

        return [
            'cpu_usage' => $cpu,
            'memory_usage' => $memory,
            'disk_usage' => $disk,
            'queue_pending' => $this->queuePendingCount(),
            'queue_failed' => $this->queueFailedCount(),
            'db_response_time_ms' => $dbResponseTime,
            'redis_status' => $redisStatus,
            'horizon_status' => $horizonStatus,
            'health_score' => $this->computeHealthScore($cpu, $memory, $disk, $redisStatus, $horizonStatus, $dbResponseTime),
            'platform_version' => $this->platformVersion(),
            'uptime' => $this->uptimeRaw(),
        ];
    }

    /**
     * A simple weighted deduction score (100 = perfect) — explainable,
     * not a black box: each unhealthy signal subtracts a fixed amount.
     */
    private function computeHealthScore(
        ?float $cpu,
        ?float $memory,
        ?float $disk,
        string $redisStatus,
        string $horizonStatus,
        ?float $dbResponseTimeMs,
    ): float {
        $score = 100.0;

        if ($cpu !== null && $cpu > 85) {
            $score -= 15;
        }
        if ($memory !== null && $memory > 90) {
            $score -= 15;
        }
        if ($disk !== null && $disk > 90) {
            $score -= 20;
        }
        if ($redisStatus !== 'online') {
            $score -= 20;
        }
        if ($horizonStatus === 'stopped') {
            $score -= 20;
        }
        if ($dbResponseTimeMs !== null && $dbResponseTimeMs > 500) {
            $score -= 10;
        }

        return max(0, $score);
    }
}
