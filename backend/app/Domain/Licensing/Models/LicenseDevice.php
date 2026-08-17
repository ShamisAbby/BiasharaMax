<?php

namespace App\Domain\Licensing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseDevice extends Model
{
    use HasUuids;

    protected $fillable = [
        'license_id',
        'hardware_fingerprint',
        'machine_name',
        'ip_address',
        'activated_at',
        'last_seen_at',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }
}
