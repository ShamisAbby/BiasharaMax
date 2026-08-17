<?php

namespace App\Domain\Support\Models;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportAgent extends Model
{
    use HasUuids;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'platform_user_id',
        'support_department_id',
        'is_active',
        'max_concurrent_tickets',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'support_department_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_agent_id');
    }

    public function openTicketsCount(): int
    {
        return $this->tickets()->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])->count();
    }
}
