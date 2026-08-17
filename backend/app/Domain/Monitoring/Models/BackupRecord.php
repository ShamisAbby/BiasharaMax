<?php

namespace App\Domain\Monitoring\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BackupRecord extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const TYPE_DATABASE = 'database';

    public const TYPE_STORAGE = 'storage';

    public const TYPE_FULL = 'full';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const TRIGGERED_MANUAL = 'manual';

    public const TRIGGERED_SCHEDULED = 'scheduled';

    protected $attributes = [
        'status' => self::STATUS_RUNNING,
        'disk' => 'local',
        'is_encrypted' => false,
        'triggered_by' => self::TRIGGERED_MANUAL,
    ];

    protected $fillable = [
        'type',
        'disk',
        'is_encrypted',
        'triggered_by',
        'status',
        'file_path',
        'file_size',
        'started_at',
        'completed_at',
        'error_message',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
