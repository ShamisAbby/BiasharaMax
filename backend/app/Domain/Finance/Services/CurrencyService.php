<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\BusinessCurrency;
use App\Domain\Localization\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

class CurrencyService
{
    /**
     * Enable a currency for a business. Creates or updates the BusinessCurrency record.
     * Pass $isPrimary = true to mark as primary (only one allowed per business).
     */
    public function enableForBusiness(
        string $businessId,
        string $currencyId,
        bool $isPrimary = false,
        ?string $rateOverride = null,
        ?string $rateAsOf = null,
    ): BusinessCurrency {
        if ($isPrimary) {
            BusinessCurrency::query()
                ->where('business_id', $businessId)
                ->update(['is_primary' => false]);
        }

        return BusinessCurrency::updateOrCreate(
            ['business_id' => $businessId, 'currency_id' => $currencyId],
            [
                'is_primary' => $isPrimary,
                'exchange_rate_override' => $rateOverride,
                'rate_as_of' => $rateAsOf,
            ],
        );
    }

    public function disableForBusiness(string $businessId, string $currencyId): void
    {
        BusinessCurrency::query()
            ->where('business_id', $businessId)
            ->where('currency_id', $currencyId)
            ->where('is_primary', false)
            ->delete();
    }

    /**
     * Returns active BusinessCurrencies for a business (with loaded Currency relation).
     *
     * @return Collection<int, BusinessCurrency>
     */
    public function activeCurrencies(string $businessId): Collection
    {
        return BusinessCurrency::query()
            ->where('business_id', $businessId)
            ->with('currency')
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();
    }

    public function baseCurrency(string $businessId): ?BusinessCurrency
    {
        return BusinessCurrency::query()
            ->where('business_id', $businessId)
            ->where('is_primary', true)
            ->with('currency')
            ->first();
    }

    /**
     * Convert an amount from one currency to another using business-level
     * effective rates (override or global). Both currencies must be enabled
     * for the business, otherwise uses global exchange_rate_to_base.
     */
    public function convert(string $amount, string $fromCurrencyId, string $toCurrencyId, string $businessId): string
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return $amount;
        }

        $fromRate = $this->effectiveRate($businessId, $fromCurrencyId);
        $toRate = $this->effectiveRate($businessId, $toCurrencyId);

        // Convert to base, then to target
        $inBase = bcmul($amount, $fromRate, 6);

        if (bccomp($toRate, '0', 6) === 0) {
            return $amount;
        }

        return bcdiv($inBase, $toRate, 2);
    }

    /** Returns the effective exchange rate (to base) for a given currency. */
    private function effectiveRate(string $businessId, string $currencyId): string
    {
        $bc = BusinessCurrency::query()
            ->where('business_id', $businessId)
            ->where('currency_id', $currencyId)
            ->first();

        if ($bc && $bc->exchange_rate_override !== null) {
            return (string) $bc->exchange_rate_override;
        }

        $currency = Currency::find($currencyId);

        return $currency ? (string) $currency->exchange_rate_to_base : '1';
    }

    /**
     * Returns all globally active currencies, with a flag indicating
     * whether the business has enabled them and the business-level rate.
     *
     * @return array<int, array{currency: Currency, business_currency: BusinessCurrency|null, effective_rate: string}>
     */
    public function allCurrenciesForBusiness(string $businessId): array
    {
        $globalCurrencies = Currency::query()->where('is_active', true)->orderBy('code')->get();
        $businessCurrencies = $this->activeCurrencies($businessId)->keyBy('currency_id');

        return $globalCurrencies->map(function (Currency $c) use ($businessCurrencies) {
            $bc = $businessCurrencies->get($c->id);

            return [
                'currency' => $c,
                'business_currency' => $bc,
                'effective_rate' => $bc ? $bc->effectiveRate() : (string) $c->exchange_rate_to_base,
            ];
        })->all();
    }
}
