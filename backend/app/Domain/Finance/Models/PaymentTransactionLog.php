<?php

namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only — see migration docblock. Never updated after creation.
 */
class PaymentTransactionLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const EVENT_CREATED = 'created';

    public const EVENT_STATUS_CHANGED = 'status_changed';

    public const EVENT_RETRIED = 'retried';

    public const EVENT_REFUNDED = 'refunded';

    public const EVENT_WEBHOOK_RECEIVED = 'webhook_received';

    public const EVENT_MANUALLY_APPROVED = 'manually_approved';

    protected $fillable = [
        'payment_transaction_id',
        'event',
        'from_status',
        'to_status',
        'message',
        'actor_type',
        'actor_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
