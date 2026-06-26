<?php

namespace App\Modules\Authentication\Models;

use App\Modules\Business\Models\Business;
use App\Modules\RBAC\Models\Role;
use App\Modules\Shared\Concerns\Auditable;
use App\Modules\Shared\Concerns\HasUserstamps;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasUserstamps, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'business_id',
        'role_id',
        'invited_by',
        'name',
        'email',
        'phone',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isOwnerOf(Business $business): bool
    {
        return $business->owner_id === $this->getKey();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->role !== null && $this->role->permissions()->where('slug', $permissionSlug)->exists();
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
