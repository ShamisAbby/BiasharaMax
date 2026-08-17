<?php

namespace App\Domain\Developer\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Business\Models\Business;
use App\Domain\Shared\Concerns\Auditable;
use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'business_id',
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): WebhookFactory
    {
        return WebhookFactory::new();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest('created_at');
    }

    public function isSubscribedTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }
}
