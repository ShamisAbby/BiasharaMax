<?php

namespace App\Domain\Shared\Models;

use App\Domain\Business\Models\Business;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const RISK_LOW = 'low';

    public const RISK_NORMAL = 'normal';

    public const RISK_ELEVATED = 'elevated';

    public const RISK_HIGH = 'high';

    protected $attributes = [
        'risk_level' => self::RISK_NORMAL,
    ];

    protected $fillable = [
        'business_id',
        'module',
        'actor_type',
        'actor_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'browser',
        'operating_system',
        'device_type',
        'country',
        'risk_level',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
