<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\SyncsMoneyMicroColumns;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * The immutable inventory ledger. Once written, a movement is never
 * updated or deleted — `delete()` is overridden below to make that a hard
 * application-level guarantee, not just a convention. Not Auditable: this
 * model IS the audit trail for stock-quantity changes.
 *
 * Two different dual-write scales apply here: `unit_cost` is a `_micros`
 * column (SyncsMoneyMicroColumns — see docs/ADR/0002-money-format-migration.md
 * Section 2), while `total_cost` is a real invoice-comparable amount and
 * stays at the standard minor-unit scale (SyncsMoneyMinorColumns).
 */
class StockMovement extends Model
{
    use BelongsToTenant, HasUuids, SyncsMoneyMicroColumns, SyncsMoneyMinorColumns;

    public const UPDATED_AT = null;

    public const TYPE_OPENING = 'opening';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SALE = 'sale';

    public const TYPE_RETURN_IN = 'return_in';

    public const TYPE_RETURN_OUT = 'return_out';

    public const TYPE_ADJUSTMENT_INCREASE = 'adjustment_increase';

    public const TYPE_ADJUSTMENT_DECREASE = 'adjustment_decrease';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const TYPE_DAMAGE = 'damage';

    public const TYPE_THEFT = 'theft';

    public const TYPE_EXPIRY = 'expiry';

    public const TYPE_COUNT_CORRECTION = 'count_correction';

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    protected $fillable = [
        'business_id',
        'branch_id',
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'product_batch_id',
        'type',
        'direction',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'unit_cost_micros',
        'total_cost',
        'total_cost_minor',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_before' => 'decimal:3',
            'quantity_after' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'unit_cost_micros' => 'integer',
            'total_cost' => 'decimal:2',
            'total_cost_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @throws LogicException always — stock movements are append-only.
     */
    public function delete(): bool
    {
        throw new LogicException('Stock movements are immutable and cannot be deleted.');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The document that triggered this movement (e.g. a future Sale,
     * PurchaseOrder, StockAdjustment, StockTransfer).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['total_cost' => 'total_cost_minor'];
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMicroColumns(): array
    {
        return ['unit_cost' => 'unit_cost_micros'];
    }
}
