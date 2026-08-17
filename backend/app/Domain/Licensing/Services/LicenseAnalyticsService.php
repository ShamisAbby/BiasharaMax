<?php

namespace App\Domain\Licensing\Services;

use App\Domain\Licensing\Models\License;
use Illuminate\Support\Carbon;

class LicenseAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'overview' => $this->overview(),
            'by_type' => $this->byType(),
            'expiring_soon' => $this->expiringSoon(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function overview(): array
    {
        return [
            'total' => License::query()->count(),
            'active' => License::query()->where('status', License::STATUS_ACTIVE)->count(),
            'suspended' => License::query()->where('status', License::STATUS_SUSPENDED)->count(),
            'revoked' => License::query()->where('status', License::STATUS_REVOKED)->count(),
            'expiring_in_30_days' => License::query()
                ->where('status', License::STATUS_ACTIVE)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [Carbon::now(), Carbon::now()->addDays(30)])
                ->count(),
        ];
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    public function byType(): array
    {
        return License::query()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->get()
            ->map(fn ($row) => ['label' => ucfirst($row->type), 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, License>
     */
    public function expiringSoon()
    {
        return License::query()
            ->where('status', License::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [Carbon::now(), Carbon::now()->addDays(30)])
            ->with('business')
            ->orderBy('expires_at')
            ->limit(20)
            ->get();
    }
}
