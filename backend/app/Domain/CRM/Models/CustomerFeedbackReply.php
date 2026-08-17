<?php

namespace App\Domain\CRM\Models;

use App\Domain\Authentication\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFeedbackReply extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_feedback_id',
        'author_id',
        'body',
    ];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(CustomerFeedback::class, 'customer_feedback_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
