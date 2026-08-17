<?php

namespace App\Domain\Security\Services;

use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Models\FailedLoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use Illuminate\Support\Carbon;

/**
 * A simple weighted deduction score (100 = perfect), mirroring the same
 * explainable approach used for platform system health
 * (see SystemMetricsService) — every deduction maps to a real, named
 * signal from the Security module, never an opaque or fabricated number.
 */
class SecurityScoreService
{
    /**
     * @return array{score: float, signals: array<int, array{label: string, count: int, deduction: float}>}
     */
    public function compute(): array
    {
        $unresolvedCritical = SecurityAlert::query()
            ->where('is_resolved', false)
            ->where('severity', SecurityAlert::SEVERITY_CRITICAL)
            ->count();

        $unresolvedHigh = SecurityAlert::query()
            ->where('is_resolved', false)
            ->where('severity', SecurityAlert::SEVERITY_HIGH)
            ->count();

        $unresolvedMedium = SecurityAlert::query()
            ->where('is_resolved', false)
            ->where('severity', SecurityAlert::SEVERITY_MEDIUM)
            ->count();

        $activeLockouts = AccountLockout::query()
            ->whereNull('unlocked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->count();

        $failedLogins24h = FailedLoginAttempt::query()
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        $signals = [
            ['label' => 'Unresolved critical alerts', 'count' => $unresolvedCritical, 'deduction' => min(40, $unresolvedCritical * 20)],
            ['label' => 'Unresolved high alerts', 'count' => $unresolvedHigh, 'deduction' => min(30, $unresolvedHigh * 10)],
            ['label' => 'Unresolved medium alerts', 'count' => $unresolvedMedium, 'deduction' => min(15, $unresolvedMedium * 3)],
            ['label' => 'Active account lockouts', 'count' => $activeLockouts, 'deduction' => min(15, $activeLockouts * 5)],
            ['label' => 'Failed logins (24h)', 'count' => $failedLogins24h, 'deduction' => $failedLogins24h > 20 ? 10 : 0],
        ];

        $score = max(0.0, 100.0 - array_sum(array_column($signals, 'deduction')));

        return [
            'score' => $score,
            'signals' => array_values(array_filter($signals, fn ($signal) => $signal['count'] > 0)),
        ];
    }
}
