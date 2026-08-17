<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Shared\Concerns\Auditable;
use Database\Factories\NotificationCampaignFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationCampaign extends Model
{
    use Auditable, HasFactory, HasUuids;

    public const AUDIENCE_ALL_BUSINESSES = 'all_businesses';

    public const AUDIENCE_BUSINESS_TYPE = 'business_type';

    public const AUDIENCE_SUBSCRIPTION_PLAN = 'subscription_plan';

    public const AUDIENCE_SPECIFIC_BUSINESSES = 'specific_businesses';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'total_recipients' => 0,
        'sent_count' => 0,
        'failed_count' => 0,
    ];

    protected $fillable = [
        'notification_template_id',
        'name',
        'channel',
        'subject',
        'body',
        'audience_type',
        'audience_filter',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'sent_count',
        'failed_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience_filter' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    protected static function newFactory(): NotificationCampaignFactory
    {
        return NotificationCampaignFactory::new();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }
}
