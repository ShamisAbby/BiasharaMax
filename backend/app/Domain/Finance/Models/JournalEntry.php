<?php

namespace App\Domain\Finance\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_VOIDED = 'voided';

    public const TYPE_AUTO = 'auto';

    public const TYPE_MANUAL = 'manual';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'type' => self::TYPE_MANUAL,
    ];

    protected $fillable = [
        'business_id',
        'entry_number',
        'entry_date',
        'status',
        'type',
        'description',
        'memo',
        'source_id',
        'source_type',
        'reversed_journal_entry_id',
        'reversal_of_id',
        'posted_by',
        'posted_at',
        'voided_by',
        'voided_at',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversedJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_journal_entry_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }
}
