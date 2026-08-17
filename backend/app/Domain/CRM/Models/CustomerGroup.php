<?php

namespace App\Domain\CRM\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerGroup extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    protected $attributes = [
        'is_vip' => false,
        'discount_percentage' => 0,
    ];

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'description',
        'is_vip',
        'discount_percentage',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'discount_percentage' => 'decimal:2',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(\App\Domain\Sales\Models\Customer::class);
    }
}
