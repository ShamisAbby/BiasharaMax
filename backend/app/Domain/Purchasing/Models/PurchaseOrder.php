<?php

namespace App\Domain\Purchasing\Models;

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

class PurchaseOrder extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_FULLY_RECEIVED = 'fully_received';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CLOSED = 'closed';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const PAYMENT_STATUS_PARTIAL = 'partial';

    public const PAYMENT_STATUS_PAID = 'paid';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'subtotal' => 0,
        'subtotal_minor' => 0,
        'discount_amount' => 0,
        'discount_amount_minor' => 0,
        'tax_amount' => 0,
        'tax_amount_minor' => 0,
        'shipping_cost' => 0,
        'shipping_cost_minor' => 0,
        'other_charges' => 0,
        'other_charges_minor' => 0,
        'total_amount' => 0,
        'total_amount_minor' => 0,
        'paid_amount' => 0,
        'paid_amount_minor' => 0,
        'balance_due' => 0,
        'balance_due_minor' => 0,
        'payment_status' => self::PAYMENT_STATUS_UNPAID,
    ];

    protected $fillable = [
        'business_id',
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'po_number',
        'status',
        'order_date',
        'expected_delivery_date',
        'subtotal',
        'subtotal_minor',
        'discount_amount',
        'discount_amount_minor',
        'tax_amount',
        'tax_amount_minor',
        'shipping_cost',
        'shipping_cost_minor',
        'other_charges',
        'other_charges_minor',
        'total_amount',
        'total_amount_minor',
        'paid_amount',
        'paid_amount_minor',
        'balance_due',
        'balance_due_minor',
        'payment_status',
        'notes',
        'terms',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'sent_at',
        'cancellation_reason',
        'cancelled_at',
        'closed_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'subtotal_minor' => 'integer',
            'discount_amount' => 'decimal:2',
            'discount_amount_minor' => 'integer',
            'tax_amount' => 'decimal:2',
            'tax_amount_minor' => 'integer',
            'shipping_cost' => 'decimal:2',
            'shipping_cost_minor' => 'integer',
            'other_charges' => 'decimal:2',
            'other_charges_minor' => 'integer',
            'total_amount' => 'decimal:2',
            'total_amount_minor' => 'integer',
            'paid_amount' => 'decimal:2',
            'paid_amount_minor' => 'integer',
            'balance_due' => 'decimal:2',
            'balance_due_minor' => 'integer',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceivedNotes(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function isFullyReceived(): bool
    {
        return $this->items->every(fn (PurchaseOrderItem $item) => bccomp((string) $item->quantity_received, (string) $item->quantity_ordered, 3) >= 0);
    }

    public function hasAnyReceived(): bool
    {
        return $this->items->contains(fn (PurchaseOrderItem $item) => bccomp((string) $item->quantity_received, '0', 3) > 0);
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
            'shipping_cost' => 'shipping_cost_minor',
            'other_charges' => 'other_charges_minor',
            'total_amount' => 'total_amount_minor',
            'paid_amount' => 'paid_amount_minor',
            'balance_due' => 'balance_due_minor',
        ];
    }
}
