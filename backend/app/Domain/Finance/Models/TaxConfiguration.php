<?php

namespace App\Domain\Finance\Models;

use App\Domain\Localization\Models\TaxRate;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxConfiguration extends Model
{
    use BelongsToTenant, HasUserstamps, HasUuids;

    protected $table = 'business_tax_configurations';

    public const TYPE_VAT = 'vat';

    public const TYPE_GST = 'gst';

    public const TYPE_SALES_TAX = 'sales_tax';

    public const TYPE_INCOME_TAX = 'income_tax';

    public const TYPE_WITHHOLDING = 'withholding';

    public const APPLIES_SALES = 'sales';

    public const APPLIES_PURCHASES = 'purchases';

    public const APPLIES_BOTH = 'both';

    protected $fillable = [
        'business_id',
        'tax_rate_id',
        'tax_type',
        'applies_to',
        'account_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TaxTransaction::class, 'tax_config_id');
    }
}
