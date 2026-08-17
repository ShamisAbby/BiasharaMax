<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Persisted state for one derived platform notification.
 *
 * Deliberately not a notification itself — the content still comes from
 * PlatformNotificationService each poll. This only remembers the two
 * things a recomputed feed cannot know about itself: whether it has been
 * emailed, and whether an operator has dismissed it.
 *
 * @property string $notification_key
 * @property string $type
 * @property string $severity
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon|null $emailed_at
 * @property \Illuminate\Support\Carbon|null $dismissed_at
 * @property string|null $dismissed_by
 */
class PlatformNotificationState extends Model
{
    use HasUuids;

    protected $fillable = [
        'notification_key',
        'type',
        'severity',
        'first_seen_at',
        'emailed_at',
        'dismissed_at',
        'dismissed_by',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'emailed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function isDismissed(): bool
    {
        return $this->dismissed_at !== null;
    }

    public function hasBeenEmailed(): bool
    {
        return $this->emailed_at !== null;
    }
}
