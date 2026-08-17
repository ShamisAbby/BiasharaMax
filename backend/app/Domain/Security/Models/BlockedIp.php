<?php

namespace App\Domain\Security\Models;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedIp extends Model
{
    use HasUuids;

    protected $attributes = [
        'is_permanent' => true,
    ];

    protected $fillable = [
        'ip_address',
        'reason',
        'blocked_by',
        'is_permanent',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_permanent' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'blocked_by');
    }

    public function isActive(): bool
    {
        return $this->is_permanent || $this->expires_at === null || $this->expires_at->isFuture();
    }
}
