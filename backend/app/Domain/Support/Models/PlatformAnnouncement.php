<?php

namespace App\Domain\Support\Models;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAnnouncement extends Model
{
    use HasUuids;

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_BUSINESSES = 'businesses';

    public const AUDIENCE_PLATFORM_STAFF = 'platform_staff';

    protected $attributes = [
        'audience' => self::AUDIENCE_ALL,
        'is_active' => true,
    ];

    protected $fillable = [
        'title',
        'body',
        'audience',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        return (! $this->starts_at || $this->starts_at->lte($now))
            && (! $this->ends_at || $this->ends_at->gte($now));
    }
}
