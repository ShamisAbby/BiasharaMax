import Card from '@/Components/Card';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { Account } from '@/types/finance';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface LedgerLine {
    id: string;
    debit: string;
    credit: string;
    description: string | null;
    running_balance: string;
    journal_entry: {
        id: string;
        entry_number: string;
        entry_date: string;
        description: string | null;
        source_type: string | null;
        source_id: string | null;
    };
}

interface PaginatedLedger {
    data: LedgerLine[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export default function LedgerShow({
    account,
    ledger,
    opening_balance,
    filters,
}: {
    account: Account;
    ledger: PaginatedLedger;
    opening_balance: string;
    filters: { from: string | null; to: string | null };
}) {
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('finance.ledger.show', account.id),
            { from: from || undefined, to: to || undefined },
            { preserveState: true },
        );
    };

    const isDebitNormal = account.normal_balance === 'debit';

    return (
        <FinanceLayout title={`Ledger — ${account.name}`}>
            <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <Link
                    href={route('finance.ledger.index')}
                    className="hover:underline"
                >
                    General Ledger
                </Link>
                <span>/</span>
                <span className="font-medium text-gray-900 dark:text-gray-100">
                    {account.code} — {account.name}
                </span>
            </div>

            <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-500 dark:text-gray-400">
                        From
                    </label>
                    <TextInput
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 dark:text-gray-400">
                        To
                    </label>
                    <TextInput
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                    />
                </div>
                <SecondaryButton type="submit">Apply</SecondaryButton>
                {(from || to) && (
                    <SecondaryButton
                        type="button"
                        onClick={() => {
                            setFrom('');
                            setTo('');
                            router.get(
                                route('finance.ledger.show', account.id),
                                {},
                                { preserveState: true },
                            );
                        }}
                    >
                        Clear
                    </SecondaryButton>
                )}
            </form>

            <Card
                title={`${account.code} — ${account.name}`}
                description={`Type: ${account.type} · Normal balance: ${account.normal_balance}`}
            >
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th className="px-4 py-3">Date</th>
                                <th className="px-4 py-3">Entry #</th>
                                <th className="px-4 py-3">Description</th>
                                <th className="px-4 py-3 text-right">Debit</th>
                                <th className="px-4 py-3 text-right">Credit</th>
                                <th className="px-4 py-3 text-right">
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                            {filters.from && (
                                <tr className="bg-gray-50 text-xs text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                                    <td className="px-4 py-2" colSpan={5}>
                                        Opening balance
                                    </td>
                                    <td className="px-4 py-2 text-right font-medium tabular-nums">
                                        {formatCurrency(
                                            parseFloat(opening_balance),
                                        )}
                                    </td>
                                </tr>
                            )}
                            {ledger.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        No transactions found for this period.
                                    </td>
                                </tr>
                            ) : (
                                ledger.data.map((line) => {
                                    const hasDebit = parseFloat(line.debit) > 0;
                                    const hasCredit =
                                        parseFloat(line.credit) > 0;
                                    const balance = parseFloat(
                                        line.running_balance,
                                    );

                                    return (
                                        <tr
                                            key={line.id}
                                            className="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                        >
                                            <td className="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                                                {line.journal_entry.entry_date}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <Link
                                                    href={route(
                                                        'finance.journal.show',
                                                        line.journal_entry.id,
                                                    )}
                                                    className="font-mono text-indigo-600 hover:underline dark:text-indigo-400"
                                                >
                                                    {
                                                        line.journal_entry
                                                            .entry_number
                                                    }
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                {line.description ??
                                                    line.journal_entry
                                                        .description ??
                                                    '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                                {hasDebit
                                                    ? formatCurrency(
                                                          parseFloat(
                                                              line.debit,
                                                          ),
                                                      )
                                                    : ''}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                                {hasCredit
                                                    ? formatCurrency(
                                                          parseFloat(
                                                              line.credit,
                                                          ),
                                                      )
                                                    : ''}
                                            </td>
                                            <td
                                                className={`px-4 py-3 text-right font-medium tabular-nums ${
                                                    isDebitNormal
                                                        ? balance >= 0
                                                            ? 'text-gray-900 dark:text-gray-100'
                                                            : 'text-red-600 dark:text-red-400'
                                                        : balance >= 0
                                                          ? 'text-gray-900 dark:text-gray-100'
                                                          : 'text-red-600 dark:text-red-400'
                                                }`}
                                            >
                                                {formatCurrency(
                                                    Math.abs(balance),
                                                )}
                                                {balance < 0
                                                    ? ' Dr'
                                                    : isDebitNormal
                                                      ? ' Dr'
                                                      : ' Cr'}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {ledger.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between border-t border-gray-200 px-4 pt-4 dark:border-gray-700">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Showing{' '}
                            {(ledger.current_page - 1) * ledger.per_page + 1}–
                            {Math.min(
                                ledger.current_page * ledger.per_page,
                                ledger.total,
                            )}{' '}
                            of {ledger.total}
                        </p>
                        <div className="flex gap-2">
                            {ledger.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'}`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="rounded px-3 py-1 text-sm text-gray-400"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </div>
                    </div>
                )}
            </Card>
        </FinanceLayout>
    );
}
