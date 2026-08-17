<?php

namespace App\Domain\Localization\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory, HasUuids;

    protected $attributes = [
        'exchange_rate_to_base' => 1,
        'is_base' => false,
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'exchange_rate_to_base',
        'is_base',
        'is_active',
        'rate_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate_to_base' => 'decimal:6',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'rate_updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }

    public function convertToBase(string $amount): string
    {
        return bcmul($amount, (string) $this->exchange_rate_to_base, 2);
    }
}
