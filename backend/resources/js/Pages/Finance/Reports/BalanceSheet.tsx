import Card from '@/Components/Card';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { Account } from '@/types/finance';
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ReportNav } from './TrialBalance';

interface AccountBalance {
    account: Account;
    balance: string;
}

interface EquityRow {
    name: string;
    balance: string;
}

interface BalanceSheetReport {
    as_of_date: string;
    assets: AccountBalance[];
    total_assets: string;
    liabilities: AccountBalance[];
    total_liabilities: string;
    equity: EquityRow[];
    total_equity: string;
    is_balanced: boolean;
}

export default function BalanceSheet({
    report,
}: {
    report: BalanceSheetReport;
}) {
    const [asOf, setAsOf] = useState(report.as_of_date);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('finance.reports.balance-sheet'),
            { as_of: asOf },
            { preserveState: true },
        );
    };

    const SectionRow = ({
        label,
        value,
        indent = false,
        accountId,
    }: {
        label: string;
        value: string;
        indent?: boolean;
        accountId?: string;
    }) => (
        <div
            className={`flex items-center justify-between py-1.5 text-sm text-gray-700 dark:text-gray-300 ${indent ? 'pl-4' : ''}`}
        >
            <span>
                {accountId ? (
                    <Link
                        href={route('finance.ledger.show', accountId)}
                        className="text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        {label}
                    </Link>
                ) : (
                    label
                )}
            </span>
            <span className="tabular-nums">
                {formatCurrency(parseFloat(value))}
            </span>
        </div>
    );

    const TotalRow = ({ label, value }: { label: string; value: string }) => (
        <div className="flex items-center justify-between border-t border-gray-200 py-2 font-semibold text-gray-900 dark:border-gray-600 dark:text-gray-100">
            <span>{label}</span>
            <span className="tabular-nums">
                {formatCurrency(parseFloat(value))}
            </span>
        </div>
    );

    return (
        <FinanceLayout title="Balance Sheet">
            <ReportNav active="balance-sheet" />

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
                    href={route('finance.reports.balance-sheet.pdf', {
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
                title="Balance Sheet"
                description={`As of ${report.as_of_date}`}
            >
                {!report.is_balanced && (
                    <div className="mb-4 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300">
                        Warning: Assets ≠ Liabilities + Equity. The balance
                        sheet does not balance.
                    </div>
                )}

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <h3 className="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Assets
                        </h3>
                        {report.assets.length > 0 ? (
                            report.assets.map(({ account, balance }) => (
                                <SectionRow
                                    key={account.id}
                                    label={account.name}
                                    value={balance}
                                    indent
                                    accountId={account.id}
                                />
                            ))
                        ) : (
                            <p className="text-sm text-gray-400">
                                No asset balances.
                            </p>
                        )}
                        <TotalRow
                            label="Total Assets"
                            value={report.total_assets}
                        />
                    </div>

                    <div className="space-y-6">
                        <div>
                            <h3 className="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Liabilities
                            </h3>
                            {report.liabilities.length > 0 ? (
                                report.liabilities.map(
                                    ({ account, balance }) => (
                                        <SectionRow
                                            key={account.id}
                                            label={account.name}
                                            value={balance}
                                            indent
                                            accountId={account.id}
                                        />
                                    ),
                                )
                            ) : (
                                <p className="text-sm text-gray-400">
                                    No liability balances.
                                </p>
                            )}
                            <TotalRow
                                label="Total Liabilities"
                                value={report.total_liabilities}
                            />
                        </div>

                        <div>
                            <h3 className="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Equity
                            </h3>
                            {report.equity.map((row, i) => (
                                <SectionRow
                                    key={i}
                                    label={row.name}
                                    value={row.balance}
                                    indent
                                />
                            ))}
                            <TotalRow
                                label="Total Equity"
                                value={report.total_equity}
                            />
                        </div>

                        <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-800">
                            <div className="flex items-center justify-between font-bold text-gray-900 dark:text-gray-100">
                                <span>Total Liabilities + Equity</span>
                                <span className="tabular-nums">
                                    {formatCurrency(
                                        parseFloat(report.total_liabilities) +
                                            parseFloat(report.total_equity),
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </FinanceLayout>
    );
}
