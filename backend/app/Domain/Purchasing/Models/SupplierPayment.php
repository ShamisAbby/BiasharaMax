<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    use HasUuids, SyncsMoneyMinorColumns;

    public const UPDATED_AT = null;

    public const METHOD_CASH = 'cash';

    public const METHOD_MOBILE_MONEY = 'mobile_money';

    public const METHOD_CARD = 'card';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    protected $attributes = [
        'payment_method' => self::METHOD_CASH,
    ];

    protected $fillable = [
        'business_id',
        'purchase_order_id',
        'supplier_id',
        'amount',
        'amount_minor',
        'payment_method',
        'reference_number',
        'paid_at',
        'notes',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['amount' => 'amount_minor'];
    }

    /**
     * No `business` relation of its own — resolve currency via the
     * supplier the payment was made to.
     */
    protected function moneyMinorCurrency(): string
    {
        return $this->supplier?->business?->currency ?? 'TZS';
    }
}
