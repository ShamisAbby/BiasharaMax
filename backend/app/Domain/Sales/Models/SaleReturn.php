<?php

namespace App\Domain\Sales\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturn extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const REASON_DAMAGED = 'damaged';

    public const REASON_WRONG_ITEM = 'wrong_item';

    public const REASON_EXPIRED = 'expired';

    public const REASON_DEFECTIVE = 'defective';

    public const REASON_CHANGED_MIND = 'changed_mind';

    public const REASON_OTHER = 'other';

    public const REFUND_CASH = 'cash';

    public const REFUND_BANK_TRANSFER = 'bank_transfer';

    public const REFUND_MOBILE_MONEY = 'mobile_money';

    public const REFUND_CARD = 'card';

    /**
     * No separate wallet ledger exists in BiasharaMax yet — store credit
     * is recorded against the same Customer.current_balance ledger that
     * already tracks debt (a negative balance there means the business
     * owes the customer), rather than inventing a new wallet table.
     */
    public const REFUND_STORE_CREDIT = 'store_credit';

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'refund_amount' => 0,
        'refund_amount_minor' => 0,
    ];

    protected $fillable = [
        'business_id',
        'sale_id',
        'customer_id',
        'branch_id',
        'warehouse_id',
        'return_number',
        'status',
        'reason',
        'refund_method',
        'refund_amount',
        'refund_amount_minor',
        'notes',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
            'refund_amount_minor' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['refund_amount' => 'refund_amount_minor'];
    }
}
