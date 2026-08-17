<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Services\TaxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function __construct(
        private readonly TaxService $service,
    ) {}

    public function configure(Request $request): Response
    {
        $this->authorize('manage', \App\Domain\Finance\Models\TaxConfiguration::class);

        $businessId = $request->user()->business_id;

        $taxRates = $this->service->availableTaxRates();
        $configs = $this->service->configurationsForBusiness($businessId);

        $liabilityAccounts = Account::query()
            ->where('business_id', $businessId)
            ->whereIn('type', [Account::TYPE_LIABILITY, Account::TYPE_ASSET])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Finance/Tax/Configure', [
            'taxRates' => $taxRates->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'rate' => $r->rate,
                'country_code' => $r->country_code,
            ]),
            'configurations' => $configs->map(fn ($c) => [
                'id' => $c->id,
                'tax_rate_id' => $c->tax_rate_id,
                'tax_type' => $c->tax_type,
                'applies_to' => $c->applies_to,
                'account_id' => $c->account_id,
                'is_active' => $c->is_active,
                'tax_rate' => ['name' => $c->taxRate->name, 'rate' => $c->taxRate->rate],
                'account' => ['code' => $c->account->code, 'name' => $c->account->name],
            ]),
            'liabilityAccounts' => $liabilityAccounts->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
            ]),
        ]);
    }

    public function saveConfigure(Request $request): RedirectResponse
    {
        $this->authorize('manage', \App\Domain\Finance\Models\TaxConfiguration::class);

        $data = $request->validate([
            'configs' => ['required', 'array'],
            'configs.*.tax_rate_id' => ['required', 'uuid', 'exists:tax_rates,id'],
            'configs.*.tax_type' => ['required', 'string', 'in:vat,gst,sales_tax,income_tax,withholding'],
            'configs.*.applies_to' => ['required', 'string', 'in:sales,purchases,both'],
            'configs.*.account_id' => ['required', 'uuid', 'exists:accounts,id'],
            'configs.*.is_active' => ['boolean'],
        ]);

        $this->service->configure(
            $request->user()->business_id,
            $data['configs'],
            $request->user()->id,
        );

        return back()->with('status', 'tax-configured');
    }

    public function vatReturn(Request $request): Response
    {
        $this->authorize('view', \App\Domain\Finance\Models\TaxConfiguration::class);

        $data = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);

        $periodStart = $data['period_start'] ?? now()->startOfQuarter()->toDateString();
        $periodEnd = $data['period_end'] ?? now()->endOfQuarter()->toDateString();

        $return = $this->service->vatReturn($request->user()->business_id, $periodStart, $periodEnd);

        return Inertia::render('Finance/Tax/VatReturn', [
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'outputTax' => $return['output_tax'],
            'inputTax' => $return['input_tax'],
            'taxDue' => $return['tax_due'],
            'transactions' => $return['transactions']->map(fn ($tx) => [
                'id' => $tx->id,
                'transaction_date' => $tx->transaction_date->toDateString(),
                'transaction_type' => $tx->transaction_type,
                'taxable_amount' => $tx->taxable_amount,
                'tax_amount' => $tx->tax_amount,
                'tax_rate_name' => $tx->taxConfig?->taxRate?->name ?? '—',
            ]),
        ]);
    }

    public function incomeTaxSummary(Request $request): Response
    {
        $this->authorize('view', \App\Domain\Finance\Models\TaxConfiguration::class);

        $fiscalYear = (int) $request->input('fiscal_year', now()->year);

        $summary = $this->service->incomeTaxSummary($request->user()->business_id, $fiscalYear);

        return Inertia::render('Finance/Tax/IncomeTax', [
            'fiscalYear' => $fiscalYear,
            'netProfit' => $summary['net_profit'],
            'estimatedTax' => $summary['estimated_tax'],
            'taxRate' => $summary['tax_rate'],
        ]);
    }
}
