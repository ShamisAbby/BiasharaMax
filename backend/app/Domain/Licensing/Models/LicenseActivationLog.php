<?php

namespace App\Domain\Licensing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only — never updated, only ever inserted. The "Activation
 * History" / "License Usage History" record for a license.
 */
class LicenseActivationLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const ACTION_GENERATED = 'generated';

    public const ACTION_ACTIVATED = 'activated';

    public const ACTION_DEACTIVATED = 'deactivated';

    public const ACTION_VALIDATED = 'validated';

    public const ACTION_RESET = 'reset';

    public const ACTION_RENEWED = 'renewed';

    public const ACTION_SUSPENDED = 'suspended';

    public const ACTION_RESTORED = 'restored';

    public const ACTION_REVOKED = 'revoked';

    public const RESULT_SUCCESS = 'success';

    public const RESULT_FAILURE = 'failure';

    protected $fillable = [
        'license_id',
        'license_device_id',
        'action',
        'result',
        'reason',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(LicenseDevice::class, 'license_device_id');
    }
}
