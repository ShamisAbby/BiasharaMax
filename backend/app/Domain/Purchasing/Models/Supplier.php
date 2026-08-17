<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Inventory\Models\Pivots\ProductSupplier;
use App\Domain\Inventory\Models\Product;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $attributes = [
        'current_balance' => 0,
        'current_balance_minor' => 0,
    ];

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'phone',
        'address',
        'status',
        'credit_limit',
        'credit_limit_minor',
        'current_balance',
        'current_balance_minor',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'credit_limit_minor' => 'integer',
            'current_balance' => 'decimal:2',
            'current_balance_minor' => 'integer',
        ];
    }

    public function currentBalanceMoney(?string $currency = null): Money
    {
        $currency ??= $this->business?->currency ?? 'TZS';

        return Money::fromMinorUnits($this->current_balance_minor ?? 0, $currency);
    }

    /**
     * Products that list this supplier as their default supplier.
     */
    public function defaultForProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'default_supplier_id');
    }

    /**
     * All products this supplier supplies, including supplier-specific
     * SKU and cost via the pivot.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
            ->using(ProductSupplier::class)
            ->withPivot(['supplier_sku', 'supplier_cost_price'])
            ->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function debtTransactions(): HasMany
    {
        return $this->hasMany(SupplierDebtTransaction::class)->latest('created_at');
    }

    /**
     * How much more credit this supplier will extend before hitting the
     * limit we've agreed with them. No limit set means no cap is enforced.
     */
    public function availableCredit(): ?string
    {
        if ($this->credit_limit === null) {
            return null;
        }

        $currency = $this->business?->currency ?? 'TZS';
        $creditLimit = Money::fromMinorUnits($this->credit_limit_minor ?? 0, $currency);

        return $creditLimit->subtract($this->currentBalanceMoney($currency))->toDecimalString();
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'credit_limit' => 'credit_limit_minor',
            'current_balance' => 'current_balance_minor',
        ];
    }
}
