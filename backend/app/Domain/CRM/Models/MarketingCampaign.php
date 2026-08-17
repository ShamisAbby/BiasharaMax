<?php

namespace App\Domain\CRM\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingCampaign extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'audience_count' => 0,
        'sent_count' => 0,
        'failed_count' => 0,
    ];

    protected $fillable = [
        'business_id',
        'name',
        'subject',
        'body',
        'status',
        'segment_filters',
        'audience_count',
        'sent_count',
        'failed_count',
        'sent_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'segment_filters' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
