<?php

namespace App\Domain\Sales\Models;

use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDebtTransaction extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    public const UPDATED_AT = null;

    public const TYPE_CHARGE = 'charge';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'business_id',
        'customer_id',
        'sale_id',
        'sale_payment_id',
        'type',
        'amount',
        'amount_minor',
        'balance_before',
        'balance_before_minor',
        'balance_after',
        'balance_after_minor',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'balance_before' => 'decimal:2',
            'balance_before_minor' => 'integer',
            'balance_after' => 'decimal:2',
            'balance_after_minor' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function salePayment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'amount' => 'amount_minor',
            'balance_before' => 'balance_before_minor',
            'balance_after' => 'balance_after_minor',
        ];
    }

    /**
     * This model has no `business` relation of its own (unlike Customer/
     * Supplier) — resolve currency via the customer it belongs to instead.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->customer?->business?->currency ?? 'TZS';
    }
}
