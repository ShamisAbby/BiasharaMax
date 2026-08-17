<?php

namespace App\Domain\Security\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    use HasUuids;

    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'device_fingerprint',
        'device_name',
        'ip_address',
        'user_agent',
        'last_used_at',
        'trusted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'trusted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
