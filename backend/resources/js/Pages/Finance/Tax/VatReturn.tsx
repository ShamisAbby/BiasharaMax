import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import PrimaryButton from '@/Components/PrimaryButton';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface TaxTransactionRow {
    id: string;
    transaction_date: string;
    transaction_type: 'output' | 'input';
    taxable_amount: number;
    tax_amount: number;
    tax_rate_name: string;
}

interface Props {
    periodStart: string;
    periodEnd: string;
    outputTax: string;
    inputTax: string;
    taxDue: string;
    transactions: TaxTransactionRow[];
}

export default function VatReturn({
    periodStart,
    periodEnd,
    outputTax,
    inputTax,
    taxDue,
    transactions,
}: Props) {
    const [from, setFrom] = useState(periodStart);
    const [to, setTo] = useState(periodEnd);

    const refresh = () => {
        router.get(route('finance.tax.vat-return'), {
            period_start: from,
            period_end: to,
        });
    };

    const taxDueNum = parseFloat(taxDue);

    return (
        <FinanceLayout title="VAT Return">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        VAT Return
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Summary of output and input tax for the selected period
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <input
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                        className="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    />
                    <span className="text-gray-400">to</span>
                    <input
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        className="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    />
                    <PrimaryButton onClick={refresh}>Update</PrimaryButton>
                </div>
            </div>

            <div className="grid grid-cols-3 gap-4">
                <Card>
                    <p className="text-sm text-gray-500">Output Tax (Sales)</p>
                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                        {formatCurrency(parseFloat(outputTax))}
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-gray-500">
                        Input Tax (Purchases)
                    </p>
                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                        {formatCurrency(parseFloat(inputTax))}
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-gray-500">Net Tax Due</p>
                    <p
                        className={`mt-1 text-2xl font-bold ${taxDueNum >= 0 ? 'text-red-600' : 'text-green-600'}`}
                    >
                        {formatCurrency(Math.abs(taxDueNum))}
                        {taxDueNum < 0 && (
                            <span className="ml-1 text-sm font-normal">
                                (refund)
                            </span>
                        )}
                    </p>
                </Card>
            </div>

            <Card>
                <p className="mb-3 text-xs text-amber-600 dark:text-amber-400">
                    Disclaimer: This is an estimate based on posted journal
                    entries. Always verify with a qualified tax advisor before
                    submitting a VAT return.
                </p>
                {transactions.length === 0 ? (
                    <p className="py-8 text-center text-sm text-gray-500">
                        No tax transactions in this period.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-700">
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Date
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Type
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Tax Rate
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Taxable Amount
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Tax Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {transactions.map((tx) => (
                                    <tr key={tx.id}>
                                        <td className="py-2 pr-4 text-gray-600">
                                            {tx.transaction_date}
                                        </td>
                                        <td className="py-2 pr-4">
                                            <Badge
                                                variant={
                                                    tx.transaction_type ===
                                                    'output'
                                                        ? 'info'
                                                        : 'success'
                                                }
                                            >
                                                {tx.transaction_type ===
                                                'output'
                                                    ? 'Output'
                                                    : 'Input'}
                                            </Badge>
                                        </td>
                                        <td className="py-2 pr-4 text-gray-600">
                                            {tx.tax_rate_name}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono">
                                            {formatCurrency(tx.taxable_amount)}
                                        </td>
                                        <td className="py-2 text-right font-mono">
                                            {formatCurrency(tx.tax_amount)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </FinanceLayout>
    );
}
