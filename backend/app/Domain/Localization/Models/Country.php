<?php

namespace App\Domain\Localization\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory, HasUuids;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'default_currency_code',
        'phone_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): CountryFactory
    {
        return CountryFactory::new();
    }
}
