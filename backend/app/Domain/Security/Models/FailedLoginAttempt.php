<?php

namespace App\Domain\Security\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FailedLoginAttempt extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'guard',
        'ip_address',
        'user_agent',
        'country',
        'reason',
    ];
}
