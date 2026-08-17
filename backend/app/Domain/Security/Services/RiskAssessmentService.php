<?php

namespace App\Domain\Security\Services;

use App\Domain\Shared\Models\AuditLog;

/**
 * Simple, explainable, rule-based heuristic — not a machine-learning
 * black box. Each rule is a concrete reason a SuperAdmin can point to;
 * nothing here is a fabricated score.
 */
class RiskAssessmentService
{
    private const SENSITIVE_MODULES = ['RBAC', 'Security', 'Finance', 'Authentication'];

    private const SENSITIVE_ACTIONS = ['deleted'];

    public function assess(string $action, string $auditableType, ?string $businessId): string
    {
        $module = $this->moduleFromClass($auditableType);

        if (in_array($action, self::SENSITIVE_ACTIONS, true) && in_array($module, self::SENSITIVE_MODULES, true)) {
            return AuditLog::RISK_HIGH;
        }

        if (str_contains($auditableType, 'PlatformRole') || str_contains($auditableType, 'Role')) {
            return AuditLog::RISK_ELEVATED;
        }

        if (in_array($module, self::SENSITIVE_MODULES, true)) {
            return AuditLog::RISK_ELEVATED;
        }

        return AuditLog::RISK_NORMAL;
    }

    private function moduleFromClass(string $class): ?string
    {
        if (preg_match('/App\\\\Domain\\\\([A-Za-z]+)\\\\/', $class, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
