<?php

namespace App\Domain\Licensing\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Shared\Concerns\Auditable;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class License extends Model
{
    use Auditable, HasFactory, HasUuids;

    public const TYPE_STARTER = 'starter';

    public const TYPE_PROFESSIONAL = 'professional';

    public const TYPE_ENTERPRISE = 'enterprise';

    public const TYPE_LIFETIME = 'lifetime';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'business_id',
        'license_key',
        'type',
        'max_devices',
        'status',
        'issued_at',
        'expires_at',
        'maintenance_expires_at',
        'offline_activation_allowed',
        'cloud_sync_enabled',
        'notes',
        'created_by',
        'revoked_at',
        'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'maintenance_expires_at' => 'datetime',
            'offline_activation_allowed' => 'boolean',
            'cloud_sync_enabled' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(LicenseDevice::class);
    }

    public function paymentTransactions(): MorphMany
    {
        return $this->morphMany(PaymentTransaction::class, 'payable');
    }

    public function activeDevices(): HasMany
    {
        return $this->devices()->whereNull('deactivated_at');
    }

    public function activationLogs(): HasMany
    {
        return $this->hasMany(LicenseActivationLog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isMaintenanceActive(): bool
    {
        return $this->maintenance_expires_at === null || $this->maintenance_expires_at->isFuture();
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    public function hasDeviceCapacity(): bool
    {
        return $this->activeDevices()->count() < $this->max_devices;
    }

    protected static function newFactory(): LicenseFactory
    {
        return LicenseFactory::new();
    }
}
