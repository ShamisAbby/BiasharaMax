<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Models\TaxConfiguration;
use App\Domain\Finance\Models\TaxTransaction;
use App\Domain\Localization\Models\TaxRate;
use Illuminate\Database\Eloquent\Collection;

class TaxService
{
    public function __construct(
        private readonly FinancialStatementService $statements,
    ) {}

    /**
     * Upsert tax configurations for a business.
     *
     * @param  array<int, array{tax_rate_id: string, tax_type: string, applies_to: string, account_id: string, is_active: bool}>  $configs
     */
    public function configure(string $businessId, array $configs, string $userId): void
    {
        foreach ($configs as $config) {
            TaxConfiguration::query()->updateOrCreate(
                [
                    'business_id' => $businessId,
                    'tax_rate_id' => $config['tax_rate_id'],
                    'tax_type' => $config['tax_type'],
                ],
                array_merge($config, ['business_id' => $businessId, 'updated_by' => $userId]),
            );
        }
    }

    public function recordTaxTransaction(
        string $businessId,
        TaxConfiguration $config,
        JournalEntry $journalEntry,
        string $transactionType,
        string $taxableAmount,
        string $taxAmount,
        string $transactionDate,
        string $periodStart,
        string $periodEnd,
        ?string $createdBy = null,
    ): TaxTransaction {
        return TaxTransaction::create([
            'business_id' => $businessId,
            'tax_config_id' => $config->id,
            'journal_entry_id' => $journalEntry->id,
            'transaction_type' => $transactionType,
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'transaction_date' => $transactionDate,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Compute VAT return for a period.
     *
     * @return array{period_start: string, period_end: string, output_tax: string, input_tax: string, tax_due: string, transactions: Collection}
     */
    public function vatReturn(string $businessId, string $periodStart, string $periodEnd): array
    {
        $transactions = TaxTransaction::query()
            ->where('business_id', $businessId)
            ->where('period_start', '>=', $periodStart)
            ->where('period_end', '<=', $periodEnd)
            ->with('taxConfig.taxRate')
            ->orderBy('transaction_date')
            ->get();

        $outputTax = '0.00';
        $inputTax = '0.00';

        foreach ($transactions as $tx) {
            if ($tx->transaction_type === TaxTransaction::TYPE_OUTPUT) {
                $outputTax = bcadd($outputTax, (string) $tx->tax_amount, 2);
            } else {
                $inputTax = bcadd($inputTax, (string) $tx->tax_amount, 2);
            }
        }

        $taxDue = bcsub($outputTax, $inputTax, 2);

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'output_tax' => $outputTax,
            'input_tax' => $inputTax,
            'tax_due' => $taxDue,
            'transactions' => $transactions,
        ];
    }

    /**
     * Estimate income tax liability for a fiscal year.
     *
     * @return array{fiscal_year: int, net_profit: string, estimated_tax: string, tax_rate: string}
     */
    public function incomeTaxSummary(string $businessId, int $fiscalYear): array
    {
        $periodStart = "{$fiscalYear}-01-01";
        $periodEnd = "{$fiscalYear}-12-31";

        $pl = $this->statements->profitAndLoss($businessId, $periodStart, $periodEnd);
        $netProfit = $pl['net_profit'] ?? '0.00';

        // Find active income_tax config to get the rate
        $config = TaxConfiguration::query()
            ->where('business_id', $businessId)
            ->where('tax_type', TaxConfiguration::TYPE_INCOME_TAX)
            ->where('is_active', true)
            ->with('taxRate')
            ->first();

        $taxRate = $config?->taxRate->rate ?? '0';
        $estimatedTax = '0.00';

        if (bccomp((string) $netProfit, '0', 2) > 0 && bccomp((string) $taxRate, '0', 2) > 0) {
            $estimatedTax = bcmul($netProfit, bcdiv((string) $taxRate, '100', 6), 2);
        }

        return [
            'fiscal_year' => $fiscalYear,
            'net_profit' => (string) $netProfit,
            'estimated_tax' => $estimatedTax,
            'tax_rate' => (string) $taxRate,
        ];
    }

    public function configurationsForBusiness(string $businessId): Collection
    {
        return TaxConfiguration::query()
            ->where('business_id', $businessId)
            ->with('taxRate', 'account')
            ->get();
    }

    public function availableTaxRates(): Collection
    {
        return TaxRate::query()->where('is_active', true)->orderBy('name')->get();
    }
}
