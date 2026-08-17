<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periodic snapshot (e.g. every 5 minutes via a scheduled command)
     * for trend charts. Live widgets (current CPU/memory/queue depth)
     * read the OS/Horizon/Redis directly at request time — they don't
     * need a table, only the historical trend does.
     */
    public function up(): void
    {
        Schema::create('system_health_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('cpu_usage', 5, 2)->nullable();
            $table->decimal('memory_usage', 5, 2)->nullable();
            $table->decimal('disk_usage', 5, 2)->nullable();
            $table->unsignedInteger('queue_pending')->nullable();
            $table->unsignedInteger('queue_failed')->nullable();
            $table->decimal('db_response_time_ms', 8, 2)->nullable();
            $table->string('redis_status', 20)->nullable();
            $table->string('horizon_status', 20)->nullable();
            $table->decimal('health_score', 5, 2)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_snapshots');
    }
};
