<?php

namespace App\Domain\Business\Services;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Models\AuditLog;

/**
 * Real activity timeline for a business owner's dashboard, built from the
 * same audit_logs table every module already writes to via the
 * Auditable trait — no separate "activity feed" table or fabricated
 * events.
 */
class RecentActivityService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(string $businessId, int $limit = 10): array
    {
        $logs = AuditLog::query()
            ->where('business_id', $businessId)
            ->latest('created_at')
            ->limit($limit)
            ->get();

        $actorIds = $logs->where('actor_type', 'user')->pluck('actor_id')->filter()->unique();
        $actors = User::query()->whereIn('id', $actorIds)->pluck('name', 'id');

        return $logs->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'actor_name' => $log->actor_type === 'user' ? $actors->get($log->actor_id, 'A team member') : 'System',
            'module' => $log->module,
            'action' => $log->action,
            'auditable_type' => $log->auditable_type ? class_basename($log->auditable_type) : null,
            'created_at' => $log->created_at,
        ])->all();
    }
}
