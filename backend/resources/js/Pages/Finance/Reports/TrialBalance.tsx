import Card from '@/Components/Card';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { Account } from '@/types/finance';
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface TrialBalanceLine {
    account: Account;
    debit: string;
    credit: string;
}

interface TrialBalanceReport {
    as_of_date: string;
    lines: TrialBalanceLine[];
    total_debit: string;
    total_credit: string;
    is_balanced: boolean;
}

export default function TrialBalance({
    report,
}: {
    report: TrialBalanceReport;
}) {
    const [asOf, setAsOf] = useState(report.as_of_date);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('finance.reports.trial-balance'),
            { as_of: asOf },
            { preserveState: true },
        );
    };

    return (
        <FinanceLayout title="Trial Balance">
            <ReportNav active="trial-balance" />

            <div className="flex flex-wrap items-end justify-between gap-3">
                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div>
                        <label className="block text-xs font-medium text-gray-500 dark:text-gray-400">
                            As of date
                        </label>
                        <TextInput
                            type="date"
                            value={asOf}
                            onChange={(e) => setAsOf(e.target.value)}
                        />
                    </div>
                    <SecondaryButton type="submit">Apply</SecondaryButton>
                </form>
                <a
                    href={route('finance.reports.trial-balance.pdf', {
                        as_of: asOf,
                    })}
                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                    target="_blank"
                    rel="noreferrer"
                >
                    <ArrowDownTrayIcon className="h-4 w-4" />
                    Download PDF
                </a>
            </div>

            <Card
                title="Trial Balance"
                description={`As of ${report.as_of_date}`}
            >
                {!report.is_balanced && (
                    <div className="mb-4 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300">
                        Warning: debits and credits do not balance — the GL may
                        have incomplete entries.
                    </div>
                )}

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th className="px-4 py-3">Code</th>
                                <th className="px-4 py-3">Account</th>
                                <th className="px-4 py-3 text-right">Debit</th>
                                <th className="px-4 py-3 text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                            {report.lines.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-8 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        No activity to report.
                                    </td>
                                </tr>
                            ) : (
                                report.lines.map(
                                    ({ account, debit, credit }) => (
                                        <tr
                                            key={account.id}
                                            className="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                        >
                                            <td className="px-4 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">
                                                {account.code}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Link
                                                    href={route(
                                                        'finance.ledger.show',
                                                        account.id,
                                                    )}
                                                    className="text-indigo-600 hover:underline dark:text-indigo-400"
                                                >
                                                    {account.name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                                {parseFloat(debit) > 0
                                                    ? formatCurrency(
                                                          parseFloat(debit),
                                                      )
                                                    : ''}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                                {parseFloat(credit) > 0
                                                    ? formatCurrency(
                                                          parseFloat(credit),
                                                      )
                                                    : ''}
                                            </td>
                                        </tr>
                                    ),
                                )
                            )}
                        </tbody>
                        <tfoot className="border-t-2 border-gray-300 dark:border-gray-600">
                            <tr className="font-semibold text-gray-900 dark:text-gray-100">
                                <td colSpan={2} className="px-4 py-3">
                                    Total
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {formatCurrency(
                                        parseFloat(report.total_debit),
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {formatCurrency(
                                        parseFloat(report.total_credit),
                                    )}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>
        </FinanceLayout>
    );
}

function ReportNav({
    active,
}: {
    active: 'trial-balance' | 'profit-and-loss' | 'balance-sheet' | 'cash-flow';
}) {
    const tabs = [
        {
            key: 'trial-balance',
            label: 'Trial Balance',
            route: 'finance.reports.trial-balance',
        },
        {
            key: 'profit-and-loss',
            label: 'Profit & Loss',
            route: 'finance.reports.profit-and-loss',
        },
        {
            key: 'balance-sheet',
            label: 'Balance Sheet',
            route: 'finance.reports.balance-sheet',
        },
        {
            key: 'cash-flow',
            label: 'Cash Flow',
            route: 'finance.reports.cash-flow',
        },
    ] as const;

    return (
        <div className="flex gap-4 border-b border-gray-200 dark:border-gray-700">
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={route(tab.route)}
                    className={`border-b-2 px-1 pb-3 text-sm font-medium ${
                        active === tab.key
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'
                    }`}
                >
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}

export { ReportNav };
