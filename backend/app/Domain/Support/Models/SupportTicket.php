<?php

namespace App\Domain\Support\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\Business;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use Auditable, HasFactory, HasUserstamps, HasUuids, SoftDeletes;

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REOPENED = 'reopened';

    protected $attributes = [
        'category' => 'other',
        'priority' => self::PRIORITY_MEDIUM,
        'status' => self::STATUS_OPEN,
    ];

    protected $fillable = [
        'ticket_number',
        'business_id',
        'opened_by_type',
        'opened_by_id',
        'support_department_id',
        'assigned_agent_id',
        'category',
        'priority',
        'status',
        'subject',
        'description',
        'satisfaction_rating',
        'satisfaction_comment',
        'first_response_at',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SupportTicketFactory
    {
        return SupportTicketFactory::new();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'support_department_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(SupportAgent::class, 'assigned_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }

    public function responseTimeMinutes(): ?int
    {
        if (! $this->first_response_at) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($this->first_response_at);
    }
}
