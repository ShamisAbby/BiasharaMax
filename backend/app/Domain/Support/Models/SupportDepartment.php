<?php

namespace App\Domain\Support\Models;

use Database\Factories\SupportDepartmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportDepartment extends Model
{
    use HasFactory, HasUuids;

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): SupportDepartmentFactory
    {
        return SupportDepartmentFactory::new();
    }

    public function agents(): HasMany
    {
        return $this->hasMany(SupportAgent::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
}
