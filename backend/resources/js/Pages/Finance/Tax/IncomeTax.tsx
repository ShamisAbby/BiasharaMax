import Card from '@/Components/Card';
import PrimaryButton from '@/Components/PrimaryButton';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    fiscalYear: number;
    netProfit: string;
    estimatedTax: string;
    taxRate: string;
}

export default function IncomeTax({
    fiscalYear,
    netProfit,
    estimatedTax,
    taxRate,
}: Props) {
    const [year, setYear] = useState(String(fiscalYear));

    const refresh = () => {
        router.get(route('finance.tax.income-tax'), { fiscal_year: year });
    };

    const profit = parseFloat(netProfit);
    const tax = parseFloat(estimatedTax);

    return (
        <FinanceLayout title="Income Tax Summary">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Income Tax Summary
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Estimated income tax liability based on your P&L
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <input
                        type="number"
                        value={year}
                        onChange={(e) => setYear(e.target.value)}
                        min={2000}
                        max={2100}
                        className="w-24 rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    />
                    <PrimaryButton onClick={refresh}>Update</PrimaryButton>
                </div>
            </div>

            <div className="grid grid-cols-3 gap-4">
                <Card>
                    <p className="text-sm text-gray-500">
                        Net Profit (FY {fiscalYear})
                    </p>
                    <p
                        className={`mt-1 text-2xl font-bold ${profit >= 0 ? 'text-green-600' : 'text-red-600'}`}
                    >
                        {formatCurrency(Math.abs(profit))}
                        {profit < 0 && (
                            <span className="ml-1 text-sm font-normal text-gray-500">
                                (loss)
                            </span>
                        )}
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-gray-500">Applied Tax Rate</p>
                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                        {parseFloat(taxRate).toFixed(2)}%
                    </p>
                    {parseFloat(taxRate) === 0 && (
                        <p className="mt-1 text-xs text-amber-600">
                            No income tax configuration found.
                        </p>
                    )}
                </Card>
                <Card>
                    <p className="text-sm text-gray-500">
                        Estimated Tax Liability
                    </p>
                    <p className="mt-1 text-2xl font-bold text-red-600">
                        {formatCurrency(tax)}
                    </p>
                </Card>
            </div>

            <Card>
                <div className="flex items-start gap-3 rounded-md bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                    <span className="mt-0.5 text-lg">⚠</span>
                    <div>
                        <p className="font-medium">Disclaimer</p>
                        <p className="mt-1">
                            This is an estimate only, computed as Net Profit ×
                            Income Tax Rate from your configured tax settings.
                            It does not account for allowable deductions, tax
                            credits, depreciation adjustments, or
                            jurisdiction-specific rules. Always consult a
                            qualified tax advisor before filing.
                        </p>
                    </div>
                </div>
            </Card>
        </FinanceLayout>
    );
}
