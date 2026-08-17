<?php

namespace App\Domain\Finance\Models;

use App\Domain\Localization\Models\Currency;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCurrency extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'business_id',
        'currency_id',
        'is_primary',
        'exchange_rate_override',
        'rate_as_of',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'exchange_rate_override' => 'decimal:6',
            'rate_as_of' => 'date',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** Returns the override rate if set, otherwise the global rate from currencies table. */
    public function effectiveRate(): string
    {
        return $this->exchange_rate_override !== null
            ? (string) $this->exchange_rate_override
            : (string) $this->currency->exchange_rate_to_base;
    }
}
