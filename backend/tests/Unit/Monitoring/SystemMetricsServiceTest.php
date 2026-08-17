<?php

namespace Tests\Unit\Monitoring;

use App\Domain\Monitoring\Services\SystemMetricsService;
use Tests\TestCase;

class SystemMetricsServiceTest extends TestCase
{
    private SystemMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SystemMetricsService::class);
    }

    public function test_db_response_time_returns_a_real_positive_number(): void
    {
        $time = $this->service->dbResponseTimeMs();

        $this->assertNotNull($time);
        $this->assertGreaterThanOrEqual(0, $time);
    }

    public function test_redis_status_returns_online_or_offline_never_throws(): void
    {
        $status = $this->service->redisStatus();

        $this->assertContains($status, ['online', 'offline']);
    }

    public function test_queue_failed_count_matches_the_real_failed_jobs_table(): void
    {
        $count = $this->service->queueFailedCount();

        $this->assertSame(\Illuminate\Support\Facades\DB::table('failed_jobs')->count(), $count);
    }

    public function test_disk_usage_is_a_real_percentage_or_null(): void
    {
        $disk = $this->service->diskUsage();

        if ($disk !== null) {
            $this->assertGreaterThanOrEqual(0, $disk);
            $this->assertLessThanOrEqual(100, $disk);
        } else {
            $this->assertNull($disk);
        }
    }

    public function test_current_snapshot_returns_a_complete_shape_without_throwing(): void
    {
        $snapshot = $this->service->currentSnapshot();

        foreach (['cpu_usage', 'memory_usage', 'disk_usage', 'queue_pending', 'queue_failed', 'db_response_time_ms', 'redis_status', 'horizon_status', 'health_score', 'platform_version', 'uptime'] as $key) {
            $this->assertArrayHasKey($key, $snapshot);
        }

        $this->assertGreaterThanOrEqual(0, $snapshot['health_score']);
        $this->assertLessThanOrEqual(100, $snapshot['health_score']);
    }
}
