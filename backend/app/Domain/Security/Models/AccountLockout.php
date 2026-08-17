<?php

namespace App\Domain\Security\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountLockout extends Model
{
    use HasUuids;

    public const TYPE_PLATFORM_USER = 'platform_user';

    public const TYPE_USER = 'user';

    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'reason',
        'locked_at',
        'unlocked_at',
        'unlocked_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * `lockable_type` stores a short discriminator ('platform_user' /
     * 'user'), matching the same convention `Auditable::resolveActor()`
     * already uses — not a full class name, so this resolves manually
     * rather than via Eloquent's `morphTo()`.
     */
    public function lockable(): PlatformUser|User|null
    {
        return match ($this->lockable_type) {
            self::TYPE_PLATFORM_USER => PlatformUser::find($this->lockable_id),
            self::TYPE_USER => User::find($this->lockable_id),
            default => null,
        };
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'unlocked_by');
    }

    public function isActive(): bool
    {
        if ($this->unlocked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
