<?php

namespace App\Domain\Security\Models;

use App\Domain\Authentication\Models\PlatformUser;
use Database\Factories\SecurityAlertFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlert extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    public const TYPE_SUSPICIOUS_LOGIN = 'suspicious_login';

    public const TYPE_BRUTE_FORCE = 'brute_force';

    public const TYPE_PERMISSION_VIOLATION = 'permission_violation';

    public const TYPE_NEW_DEVICE = 'new_device';

    public const TYPE_BLOCKED_IP_ATTEMPT = 'blocked_ip_attempt';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    protected $attributes = [
        'severity' => self::SEVERITY_LOW,
        'is_resolved' => false,
    ];

    protected $fillable = [
        'type',
        'severity',
        'subject_type',
        'subject_id',
        'ip_address',
        'description',
        'metadata',
        'is_resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SecurityAlertFactory
    {
        return SecurityAlertFactory::new();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'resolved_by');
    }
}
