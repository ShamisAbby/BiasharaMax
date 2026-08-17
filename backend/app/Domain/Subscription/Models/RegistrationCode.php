<?php

namespace App\Domain\Subscription\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\Business;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RegistrationCode extends Model
{
    use HasUuids;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_USED       = 'used';
    public const STATUS_EXPIRED    = 'expired';

    protected $fillable = [
        'code',
        'plan_id',
        'billing_cycle',
        'duration_months',
        'description',
        'status',
        'expires_at',
        'used_by',
        'used_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'used_at'    => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function usedByBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'used_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public static function generate(): string
    {
        do {
            $code = strtoupper(implode('-', [
                Str::random(4),
                Str::random(4),
                Str::random(4),
                Str::random(4),
            ]));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
