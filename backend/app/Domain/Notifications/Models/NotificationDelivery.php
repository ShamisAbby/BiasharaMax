<?php

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationDelivery extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_READ = 'read';

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $fillable = [
        'notification_campaign_id',
        'notifiable_id',
        'notifiable_type',
        'channel',
        'recipient',
        'status',
        'provider_message_id',
        'error_message',
        'retry_of_delivery_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'notification_campaign_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_delivery_id');
    }
}
