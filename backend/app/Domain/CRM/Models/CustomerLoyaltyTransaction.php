<?php

namespace App\Domain\CRM\Models;

use App\Domain\Sales\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLoyaltyTransaction extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const TYPE_EARN = 'earn';

    public const TYPE_REDEEM = 'redeem';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'business_id',
        'customer_id',
        'type',
        'points',
        'balance_before',
        'balance_after',
        'notes',
        'created_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
