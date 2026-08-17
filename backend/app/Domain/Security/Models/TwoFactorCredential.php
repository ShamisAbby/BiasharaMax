<?php

namespace App\Domain\Security\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TwoFactorCredential extends Model
{
    use HasUuids;

    public const TYPE_PLATFORM_USER = 'platform_user';

    public const TYPE_USER = 'user';

    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'secret',
        'recovery_codes',
        'confirmed_at',
        'enabled_at',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_codes' => 'encrypted:array',
            'confirmed_at' => 'datetime',
            'enabled_at' => 'datetime',
        ];
    }

    public function authenticatable(): PlatformUser|User|null
    {
        return match ($this->authenticatable_type) {
            self::TYPE_PLATFORM_USER => PlatformUser::find($this->authenticatable_id),
            self::TYPE_USER => User::find($this->authenticatable_id),
            default => null,
        };
    }

    public function isEnabled(): bool
    {
        return $this->enabled_at !== null;
    }
}
