<?php

namespace App\Domain\Shared\Concerns;

use App\Domain\Business\Models\Business;
use App\Domain\Security\Services\RiskAssessmentService;
use App\Domain\Security\Services\UserAgentParser;
use App\Domain\Shared\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes an immutable audit trail entry whenever a model is created, updated
 * or deleted. The acting guard (business user vs platform user) is resolved
 * from whichever Sanctum/session guard is currently authenticated.
 */
trait Auditable
{
    /** Attributes that must never be written to the audit trail. */
    protected static array $auditExcluded = ['password', 'remember_token'];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->writeAuditLog('created', [], $model->auditableAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $model->writeAuditLog('updated', $model->auditableAttributes($model->getOriginal()), $changes);
        });

        static::deleted(function (Model $model) {
            $model->writeAuditLog('deleted', $model->auditableAttributes(), []);
        });
    }

    protected function auditableAttributes(?array $attributes = null): array
    {
        return collect($attributes ?? $this->getAttributes())
            ->except(static::$auditExcluded)
            ->toArray();
    }

    protected function writeAuditLog(string $action, array $oldValues, array $newValues): void
    {
        [$actorType, $actorId] = $this->resolveActor();
        $userAgent = Request::userAgent();
        $parsedAgent = App::make(UserAgentParser::class)->parse($userAgent);
        $module = $this->resolveAuditModule();

        AuditLog::create([
            'business_id' => $this->resolveAuditBusinessId(),
            'module' => $module,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => $userAgent,
            'browser' => $parsedAgent['browser'],
            'operating_system' => $parsedAgent['operating_system'],
            'device_type' => $parsedAgent['device_type'],
            'risk_level' => App::make(RiskAssessmentService::class)->assess($action, static::class, $this->resolveAuditBusinessId()),
        ]);
    }

    protected function resolveAuditModule(): ?string
    {
        if (preg_match('/App\\\\Domain\\\\([A-Za-z]+)\\\\/', static::class, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function resolveAuditBusinessId(): ?string
    {
        if ($this instanceof Business) {
            return $this->getKey();
        }

        return $this->getAttribute('business_id');
    }

    /**
     * See the matching docblock on BelongsToTenant::resolveTenantBusinessId()
     * for why `sanctum` is checked before `web`. `platform` stays a
     * special-cased first check, same reasoning as
     * HasUserstamps::currentActorId().
     */
    protected function resolveActor(): array
    {
        if (Auth::guard('platform')->check()) {
            return ['platform_user', Auth::guard('platform')->id()];
        }

        $userId = Auth::guard('sanctum')->id() ?? Auth::guard('web')->id();

        return $userId !== null ? ['user', $userId] : [null, null];
    }
}
