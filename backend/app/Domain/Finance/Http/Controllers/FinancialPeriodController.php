<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Models\FinancialPeriod;
use App\Domain\Finance\Services\FinancialPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinancialPeriodController extends Controller
{
    public function __construct(
        private readonly FinancialPeriodService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FinancialPeriod::class);

        $periods = $this->service->periodsForBusiness($request->user()->business_id);

        $grouped = $periods->groupBy('fiscal_year')->sortKeysDesc();

        return Inertia::render('Finance/Periods/Index', [
            'groupedPeriods' => $grouped->map(fn ($group) => $group->map(fn (FinancialPeriod $p) => [
                'id' => $p->id,
                'fiscal_year' => $p->fiscal_year,
                'period_name' => $p->period_name,
                'period_start' => $p->period_start->toDateString(),
                'period_end' => $p->period_end->toDateString(),
                'status' => $p->status,
                'is_year_end' => $p->is_year_end,
                'locked_at' => $p->locked_at?->toDateTimeString(),
                'closed_at' => $p->closed_at?->toDateTimeString(),
            ])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage', FinancialPeriod::class);

        $data = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_name' => ['required', 'string', 'max:50'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        FinancialPeriod::create(array_merge($data, [
            'business_id' => $request->user()->business_id,
            'status' => FinancialPeriod::STATUS_OPEN,
            'created_by' => $request->user()->id,
        ]));

        return back()->with('status', 'period-created');
    }

    public function seedYear(Request $request): RedirectResponse
    {
        $this->authorize('manage', FinancialPeriod::class);

        $data = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $this->service->seedDefaultPeriods($request->user()->business_id, $data['fiscal_year']);

        return back()->with('status', 'periods-seeded');
    }

    public function lock(Request $request, FinancialPeriod $period): RedirectResponse
    {
        $this->authorize('lock', $period);

        try {
            $this->service->lock($period, $request->user()->id);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['period' => $e->getMessage()]);
        }

        return back()->with('status', 'period-locked');
    }

    public function close(Request $request, FinancialPeriod $period): RedirectResponse
    {
        $this->authorize('close', $period);

        try {
            $this->service->close($period, $request->user()->id);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['period' => $e->getMessage()]);
        }

        return back()->with('status', 'period-closed');
    }
}
