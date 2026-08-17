<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Shared\Concerns\Auditable;
use Database\Factories\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    public const CATEGORY_SYSTEM_ALERT = 'system_alert';

    public const CATEGORY_SUBSCRIPTION_REMINDER = 'subscription_reminder';

    public const CATEGORY_LICENSE_EXPIRY = 'license_expiry';

    public const CATEGORY_LOW_STOCK = 'low_stock';

    public const CATEGORY_PAYMENT_SUCCESS = 'payment_success';

    public const CATEGORY_PAYMENT_FAILURE = 'payment_failure';

    public const CATEGORY_BUSINESS_APPROVAL = 'business_approval';

    public const CATEGORY_USER_REGISTRATION = 'user_registration';

    public const CATEGORY_SUPPORT_TICKET_UPDATE = 'support_ticket_update';

    public const CATEGORY_MARKETING = 'marketing';

    public const CATEGORY_BROADCAST = 'broadcast';

    public const CATEGORY_CUSTOM = 'custom';

    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'slug',
        'channel',
        'category',
        'subject',
        'body',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): NotificationTemplateFactory
    {
        return NotificationTemplateFactory::new();
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(NotificationCampaign::class);
    }
}
