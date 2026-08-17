<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('manage', \App\Domain\Finance\Models\Account::class);

        $currencies = $this->service->allCurrenciesForBusiness($request->user()->business_id);

        return Inertia::render('Finance/Settings/Currencies', [
            'currencies' => array_values(array_map(fn ($row) => [
                'id' => $row['currency']->id,
                'code' => $row['currency']->code,
                'name' => $row['currency']->name,
                'symbol' => $row['currency']->symbol,
                'global_rate' => (string) $row['currency']->exchange_rate_to_base,
                'is_enabled' => $row['business_currency'] !== null,
                'is_primary' => $row['business_currency']?->is_primary ?? false,
                'rate_override' => $row['business_currency']?->exchange_rate_override !== null
                    ? (string) $row['business_currency']->exchange_rate_override
                    : null,
                'rate_as_of' => $row['business_currency']?->rate_as_of?->toDateString(),
                'effective_rate' => $row['effective_rate'],
            ], $currencies)),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $this->authorize('manage', \App\Domain\Finance\Models\Account::class);

        $data = $request->validate([
            'currency_id' => ['required', 'uuid', 'exists:currencies,id'],
            'is_primary' => ['boolean'],
            'rate_override' => ['nullable', 'numeric', 'min:0.000001'],
            'rate_as_of' => ['nullable', 'date'],
        ]);

        $this->service->enableForBusiness(
            $request->user()->business_id,
            $data['currency_id'],
            $data['is_primary'] ?? false,
            isset($data['rate_override']) ? (string) $data['rate_override'] : null,
            $data['rate_as_of'] ?? null,
        );

        return back()->with('status', 'currency-enabled');
    }

    public function disable(Request $request, string $currencyId): RedirectResponse
    {
        $this->authorize('manage', \App\Domain\Finance\Models\Account::class);

        $this->service->disableForBusiness($request->user()->business_id, $currencyId);

        return back()->with('status', 'currency-disabled');
    }
}
