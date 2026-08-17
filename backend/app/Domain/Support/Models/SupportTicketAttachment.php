<?php

namespace App\Domain\Support\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAttachment extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'support_ticket_id',
        'support_ticket_message_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'support_ticket_message_id');
    }
}
