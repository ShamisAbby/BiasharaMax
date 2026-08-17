<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Subscription\Models\RegistrationCode;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationCodeController extends Controller
{
    public function index(Request $request): Response
    {
        $codes = RegistrationCode::query()
            ->with(['plan:id,name', 'usedByBusiness:id,name', 'createdBy:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Platform/Subscriptions/RegistrationCodes/Index', [
            /*
             * Shaped as `{data, meta}` rather than handed over raw.
             *
             * A Laravel paginator serialises `links` at the top level and
             * has no `meta` key at all, but BiDataGrid reads
             * `paginated.meta.links` — so this page crashed on render with
             * "Cannot read properties of undefined (reading 'links')".
             *
             * Every other BiDataGrid screen either wraps it this way or
             * passes an API Resource collection, which produces the same
             * shape. This one was the odd one out.
             */
            'codes' => [
                'data' => $codes->items(),
                'meta' => [
                    'current_page' => $codes->currentPage(),
                    'last_page' => $codes->lastPage(),
                    'total' => $codes->total(),
                    'links' => $codes->linkCollection()->toArray(),
                ],
            ],
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id'         => ['required', 'uuid', 'exists:subscription_plans,id'],
            'billing_cycle'   => ['required', 'in:monthly,quarterly,yearly'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'description'     => ['nullable', 'string', 'max:255'],
            'expires_at'      => ['nullable', 'date', 'after:today'],
            'quantity'        => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $qty = (int) $validated['quantity'];
        unset($validated['quantity']);

        $platformUser = Auth::guard('platform')->id();

        for ($i = 0; $i < $qty; $i++) {
            RegistrationCode::query()->create(array_merge($validated, [
                'code'       => RegistrationCode::generate(),
                'created_by' => $platformUser,
            ]));
        }

        return back()->with('success', "{$qty} code(s) generated.");
    }

    public function destroy(RegistrationCode $registrationCode): RedirectResponse
    {
        abort_unless($registrationCode->status === RegistrationCode::STATUS_AVAILABLE, 403, 'Used codes cannot be deleted.');

        $registrationCode->delete();

        return back()->with('success', 'Code deleted.');
    }
}
