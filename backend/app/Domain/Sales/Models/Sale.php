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

class Sale extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_PARTIAL = 'partial';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const SOURCE_POS = 'pos';

    public const SOURCE_ONLINE = 'online';

    public const SOURCE_DESKTOP = 'desktop';

    protected $attributes = [
        'status' => self::STATUS_COMPLETED,
        'payment_status' => self::PAYMENT_STATUS_PAID,
        'source' => self::SOURCE_POS,
        'subtotal' => 0,
        'subtotal_minor' => 0,
        'discount_amount' => 0,
        'discount_amount_minor' => 0,
        'tax_amount' => 0,
        'tax_amount_minor' => 0,
        'total_amount' => 0,
        'total_amount_minor' => 0,
        'paid_amount' => 0,
        'paid_amount_minor' => 0,
        'balance_due' => 0,
        'balance_due_minor' => 0,
    ];

    protected $fillable = [
        'business_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'sale_number',
        'status',
        'payment_status',
        'source',
        'subtotal',
        'subtotal_minor',
        'discount_amount',
        'discount_amount_minor',
        'tax_amount',
        'tax_amount_minor',
        'total_amount',
        'total_amount_minor',
        'paid_amount',
        'paid_amount_minor',
        'balance_due',
        'balance_due_minor',
        'notes',
        'delivery_address',
        'idempotency_key',
        'voided_at',
        'voided_by',
        'void_reason',
        'sold_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'subtotal_minor' => 'integer',
            'discount_amount' => 'decimal:2',
            'discount_amount_minor' => 'integer',
            'tax_amount' => 'decimal:2',
            'tax_amount_minor' => 'integer',
            'total_amount' => 'decimal:2',
            'total_amount_minor' => 'integer',
            'paid_amount' => 'decimal:2',
            'paid_amount_minor' => 'integer',
            'balance_due' => 'decimal:2',
            'balance_due_minor' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->latest('paid_at');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'subtotal' => 'subtotal_minor',
            'discount_amount' => 'discount_amount_minor',
            'tax_amount' => 'tax_amount_minor',
            'total_amount' => 'total_amount_minor',
            'paid_amount' => 'paid_amount_minor',
            'balance_due' => 'balance_due_minor',
        ];
    }
}
