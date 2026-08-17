<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    protected $attributes = [
        'is_successful' => false,
    ];

    protected $fillable = [
        'integration_id',
        'direction',
        'event_type',
        'request_payload',
        'response_payload',
        'status_code',
        'is_successful',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'is_successful' => 'boolean',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
