<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Business\Models\Branch;
use App\Domain\Sales\Models\Customer;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Income extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const CATEGORY_SERVICE = 'service';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORY_MANUAL = 'manual';

    protected $attributes = [
        'category' => self::CATEGORY_OTHER,
        'payment_method' => 'cash',
    ];

    protected $fillable = [
        'business_id',
        'branch_id',
        'customer_id',
        'category',
        'title',
        'description',
        'amount',
        'amount_minor',
        'income_date',
        'payment_method',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'income_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['amount' => 'amount_minor'];
    }
}
