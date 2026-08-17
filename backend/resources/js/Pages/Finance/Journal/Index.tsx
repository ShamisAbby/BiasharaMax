import Badge from '@/Components/Badge';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { JournalEntry, JournalEntryStatus } from '@/types/finance';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const STATUS_VARIANT: Record<
    JournalEntryStatus,
    'neutral' | 'success' | 'warning' | 'danger'
> = {
    draft: 'warning',
    posted: 'success',
    reversed: 'neutral',
    voided: 'danger',
};

export default function JournalIndex({
    entries,
    filters,
}: {
    entries: { data: JournalEntry[] };
    filters: { search?: string; status?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('finance.journal.index'),
            { ...filters, search },
            { preserveState: true },
        );
    };

    return (
        <FinanceLayout title="Journal Entries">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={submitSearch} className="flex gap-2">
                    <TextInput
                        placeholder="Search entry number or description..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-72"
                    />
                    <SelectInput
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get(
                                route('finance.journal.index'),
                                {
                                    ...filters,
                                    status: e.target.value || undefined,
                                },
                                { preserveState: true },
                            )
                        }
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="posted">Posted</option>
                        <option value="reversed">Reversed</option>
                        <option value="voided">Voided</option>
                    </SelectInput>
                </form>
                <Link href={route('finance.journal.create')}>
                    <PrimaryButton>New Journal Entry</PrimaryButton>
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Entry #',
                                'Date',
                                'Description',
                                'Amount',
                                'Status',
                                '',
                            ].map((h) => (
                                <th
                                    key={h}
                                    className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {entries.data.map((entry) => (
                            <tr
                                key={entry.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {entry.entry_number}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {entry.entry_date}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {entry.description ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {formatCurrency(entry.total_debit)}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={STATUS_VARIANT[entry.status]}
                                    >
                                        {entry.status}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <Link
                                        href={route(
                                            'finance.journal.show',
                                            entry.id,
                                        )}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {entries.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No journal entries yet. Create one to start posting to
                        the ledger.
                    </p>
                )}
            </div>
        </FinanceLayout>
    );
}
