<?php

namespace App\Domain\Reports\Http\Controllers;

use App\Domain\Reports\Services\ReportCenterService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportCenterController
{
    /**
     * The permission that governs each report family.
     *
     * The Report Center is a side door into every module's data — the
     * Profit & Loss here is the same figure as Finance → Reports, and the
     * customer debt report is the same data as CRM. It had no permission
     * check at all, so a cashier could read the P&L by URL even though the
     * Finance module itself refuses them. Keyed on the report key's prefix,
     * which every entry in the catalog already carries.
     *
     * @var array<string, list<string>>
     */
    private const REPORT_PERMISSIONS = [
        'sales' => ['sales.view'],
        'inventory' => ['inventory.view', 'products.view'],
        'purchasing' => ['purchase_orders.view'],
        'finance' => ['finance.view', 'accounting.view'],
        'crm' => ['crm.view', 'customers.view'],
        'hr' => ['payroll.view', 'payroll.manage'],
        'ai' => ['sales.view'],
    ];

    public function __construct(
        private readonly ReportCenterService $service,
    ) {}

    public function index(Request $request): Response
    {
        $business = $request->user()->business;

        return Inertia::render('Reports/Index', [
            // Filtered rather than gated wholesale: a cashier still has a
            // legitimate Sales report hub, they just shouldn't see the
            // finance and payroll families listed in it.
            'catalog' => $this->visibleCatalog($request),
            'hubStats' => $business ? $this->service->hubStats($business->id) : null,
        ]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function visibleCatalog(Request $request): array
    {
        return collect($this->service->catalog())
            ->map(fn (array $reports): array => collect($reports)
                ->filter(fn (array $report): bool => $this->mayView($request, $report['key']))
                ->values()
                ->all())
            // Drop whole categories that end up empty so the hub doesn't
            // render a heading over nothing.
            ->filter(fn (array $reports): bool => $reports !== [])
            ->all();
    }

    private function mayView(Request $request, string $key): bool
    {
        $family = strtok($key, '.');
        $required = self::REPORT_PERMISSIONS[$family] ?? null;

        // An unrecognised family is treated as privileged rather than
        // public: a report added without a mapping here should fail closed.
        if ($required === null) {
            return false;
        }

        $user = $request->user();

        foreach ($required as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function show(Request $request, string $key): Response
    {
        // The index only *lists* what you may see; this is what stops a
        // hand-typed `/reports/finance.profit_loss` from working anyway.
        abort_unless($this->mayView($request, $key), 403);

        $business = $request->user()->business;
        $catalog = $this->service->catalog();

        $report = null;
        foreach ($catalog as $category => $reports) {
            foreach ($reports as $r) {
                if ($r['key'] === $key) {
                    $report = array_merge($r, ['category' => $category]);
                    break 2;
                }
            }
        }

        if (! $report || ! ($report['available'] ?? false)) {
            abort(404);
        }

        $filters = $request->only(['date_from', 'date_to']);

        $data = $business ? $this->service->generate($key, $business->id, $filters) : [
            'columns' => [],
            'rows' => [],
            'summary' => [],
        ];

        return Inertia::render('Reports/Show', [
            'report' => $report,
            'data' => $data,
            'filters' => array_merge([
                'date_from' => now()->subDays(30)->toDateString(),
                'date_to' => now()->toDateString(),
            ], $filters),
        ]);
    }
}
