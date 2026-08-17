<?php

namespace App\Domain\Localization\Models;

use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasFactory, HasUuids;

    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'country_code',
        'rate',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }
}
