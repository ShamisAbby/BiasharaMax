<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Localization\Models\Country;
use App\Domain\Localization\Models\Currency;
use App\Domain\Localization\Models\TaxRate;
use App\Domain\Platform\Services\EnvEditorService;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingsController extends Controller
{
    public function index(Request $request, PlatformSettingsService $settings, EnvEditorService $env): Response
    {
        return Inertia::render('Platform/System/Settings/Index', [
            'settings'          => $settings->allGrouped(),
            'envSettings'       => [
                'app'      => $env->getGroup('app'),
                'mail'     => $env->getGroup('mail'),
                'database' => $env->getGroup('database'),
            ],
            'currencies'        => Currency::query()->orderBy('code')->get(['id', 'code', 'name', 'symbol', 'exchange_rate_to_base', 'is_base', 'is_active']),
            'countries'         => Country::query()->orderBy('name')->get(['id', 'code', 'name', 'default_currency_code', 'phone_code', 'is_active']),
            'taxRates'          => TaxRate::query()->orderBy('name')->get(['id', 'name', 'country_code', 'rate', 'is_default', 'is_active']),
            'subscriptionPlans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['id', 'name']),
            'businessTypes'     => BusinessType::query()->orderBy('name')->get(['id', 'name']),
            'websiteTemplates'  => WebsiteTemplate::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateGroup(Request $request, string $group, PlatformSettingsService $settings): RedirectResponse
    {
        if (! array_key_exists($group, PlatformSettingsService::SCHEMA)) {
            abort(404);
        }

        $settings->updateGroup($group, $request->except('_token'), $request->user()->id);

        return back()->with('status', 'settings-updated');
    }

    public function uploadFile(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:2048'],
            'group' => ['required', 'string'],
            'key' => ['required', 'string'],
        ]);

        $url = $settings->storeUploadedFile($request->file('file'));
        $settings->updateGroup($validated['group'], [$validated['key'] => $url], $request->user()->id);

        return back()->with('status', 'file-uploaded');
    }

    public function storeCurrency(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'exchange_rate_to_base' => ['required', 'numeric', 'min:0'],
        ]);

        Currency::query()->create([...$validated, 'rate_updated_at' => now()]);

        return back()->with('status', 'currency-created');
    }

    public function updateCurrency(Request $request, Currency $currency): RedirectResponse
    {
        $validated = $request->validate([
            'exchange_rate_to_base' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $currency->update([...$validated, 'rate_updated_at' => now()]);

        return back()->with('status', 'currency-updated');
    }

    public function storeTaxRate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['boolean'],
        ]);

        TaxRate::query()->create($validated);

        return back()->with('status', 'tax-rate-created');
    }

    public function updateTaxRate(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $taxRate->update($validated);

        return back()->with('status', 'tax-rate-updated');
    }

    public function destroyTaxRate(TaxRate $taxRate): RedirectResponse
    {
        $taxRate->delete();

        return back()->with('status', 'tax-rate-deleted');
    }
}
