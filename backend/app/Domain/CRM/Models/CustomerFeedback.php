<?php

namespace App\Domain\CRM\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerFeedback extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    public const TYPE_RATING = 'rating';

    public const TYPE_REVIEW = 'review';

    public const TYPE_COMPLAINT = 'complaint';

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'customer_feedback';

    protected $attributes = [
        'type' => self::TYPE_REVIEW,
        'status' => self::STATUS_OPEN,
    ];

    protected $fillable = [
        'business_id',
        'customer_id',
        'branch_id',
        'type',
        'rating',
        'subject',
        'body',
        'status',
        'assigned_to',
        'resolved_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Sales\Models\Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CustomerFeedbackReply::class)->orderBy('created_at');
    }
}
