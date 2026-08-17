<?php

namespace App\Domain\Finance\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialPeriod extends Model
{
    use BelongsToTenant, HasUserstamps, HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'business_id',
        'fiscal_year',
        'period_name',
        'period_start',
        'period_end',
        'status',
        'is_year_end',
        'locked_by',
        'locked_at',
        'closed_by',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'is_year_end' => 'boolean',
            'locked_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function closingEntries(): HasMany
    {
        return $this->hasMany(PeriodClosingEntry::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
