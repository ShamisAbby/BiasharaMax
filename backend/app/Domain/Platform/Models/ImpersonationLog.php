<?php

namespace App\Domain\Platform\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail of every "log in as this business owner" action
 * a SuperAdmin takes. This is the accountability record for an otherwise
 * high-risk capability — every impersonation session is attributable to a
 * specific platform user, business and time window.
 */
class ImpersonationLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'platform_user_id',
        'user_id',
        'business_id',
        'ip_address',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
