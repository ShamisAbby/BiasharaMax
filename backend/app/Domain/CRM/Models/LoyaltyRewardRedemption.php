<?php

namespace App\Domain\CRM\Models;

use App\Domain\Sales\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyRewardRedemption extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $fillable = [
        'business_id',
        'customer_id',
        'loyalty_reward_id',
        'points_spent',
        'status',
        'redeemed_at',
        'fulfilled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }
}
