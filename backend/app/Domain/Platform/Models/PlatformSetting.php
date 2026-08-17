<?php

namespace App\Domain\Platform\Models;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    use HasUuids;

    public const KEY_PASSWORD_MIN_LENGTH = 'security.password_min_length';

    public const KEY_LOCKOUT_THRESHOLD = 'security.lockout_threshold';

    public const KEY_AUDIT_RETENTION_DAYS = 'audit.retention_days';

    public const KEY_MONITORING_SNAPSHOT_INTERVAL_MINUTES = 'monitoring.snapshot_interval_minutes';

    protected $fillable = [
        'key',
        'value',
        'description',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'updated_by');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("platform_setting:{$key}", 300, function () use ($key, $default) {
            $setting = static::query()->where('key', $key)->first();

            return $setting?->value['value'] ?? $default;
        });
    }

    public static function set(string $key, mixed $value, ?string $updatedBy = null): self
    {
        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value], 'updated_by' => $updatedBy],
        );

        Cache::forget("platform_setting:{$key}");

        return $setting;
    }
}
