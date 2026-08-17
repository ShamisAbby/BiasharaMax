<?php

namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only — see migration docblock. Never updated after creation.
 */
class PaymentGatewayLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const EVENT_WEBHOOK = 'webhook';

    public const EVENT_CHARGE = 'charge';

    public const EVENT_REFUND = 'refund';

    public const EVENT_VERIFY = 'verify';

    public const EVENT_HEALTH_CHECK = 'health_check';

    protected $fillable = [
        'payment_gateway_id',
        'payment_transaction_id',
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

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
