<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDebtTransaction extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    public const UPDATED_AT = null;

    public const TYPE_BILL = 'bill';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'business_id',
        'supplier_id',
        'purchase_order_id',
        'supplier_payment_id',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
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
     * Supplier) — resolve currency via the supplier it belongs to instead.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->supplier?->business?->currency ?? 'TZS';
    }
}
