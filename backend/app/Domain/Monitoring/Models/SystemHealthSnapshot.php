<?php

namespace App\Domain\Monitoring\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SystemHealthSnapshot extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'queue_pending',
        'queue_failed',
        'db_response_time_ms',
        'redis_status',
        'horizon_status',
        'health_score',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }
}
