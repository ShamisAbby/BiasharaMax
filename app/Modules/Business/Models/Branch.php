<?php

namespace App\Modules\Business\Models;

use App\Modules\Authentication\Models\User;
use App\Modules\Shared\Concerns\Auditable;
use App\Modules\Shared\Concerns\BelongsToTenant;
use App\Modules\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'code',
        'is_main',
        'phone',
        'address',
        'city',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
        ];
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
