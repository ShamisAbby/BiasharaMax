import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PlatformFinanceLayout from '@/Layouts/PlatformFinanceLayout';
import { router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface CatalogEntry {
    key: string;
    label: string;
    category: string;
    available: boolean;
    unavailable_reason: string | null;
}

interface ReportData {
    columns: string[];
    rows: (string | number | null)[][];
    summary?: Record<string, number>;
}

export default function ReportsIndex({
    catalog,
    selectedReport,
    filters,
    report,
}: {
    catalog: CatalogEntry[];
    selectedReport: string;
    filters: Record<string, string>;
    report: ReportData;
}) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const categories = Array.from(new Set(catalog.map((c) => c.category)));

    const selectReport = (key: string) => {
        const entry = catalog.find((c) => c.key === key);
        if (!entry?.available) return;

        router.get(
            route('platform.finance.reports.index'),
            { report: key, date_from: dateFrom, date_to: dateTo },
            { preserveState: true },
        );
    };

    const applyDateFilter: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('platform.finance.reports.index'),
            { report: selectedReport, date_from: dateFrom, date_to: dateTo },
            { preserveState: true },
        );
    };

    const exportUrl = (format: 'csv' | 'pdf') =>
        route(`platform.finance.reports.export.${format}`, {
            report: selectedReport,
            date_from: dateFrom,
            date_to: dateTo,
        });

    const currentEntry = catalog.find((c) => c.key === selectedReport);

    return (
        <PlatformFinanceLayout title="Reports">
            <div className="grid gap-6 lg:grid-cols-4">
                <div className="space-y-4 lg:col-span-1">
                    {categories.map((category) => (
                        <BiCard key={category} title={category}>
                            <div className="space-y-1">
                                {catalog
                                    .filter((c) => c.category === category)
                                    .map((entry) => (
                                        <button
                                            key={entry.key}
                                            onClick={() =>
                                                selectReport(entry.key)
                                            }
                                            disabled={!entry.available}
                                            title={
                                                entry.unavailable_reason ??
                                                undefined
                                            }
                                            className={`block w-full rounded-md px-3 py-2 text-left text-sm ${
                                                entry.key === selectedReport
                                                    ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                    : entry.available
                                                      ? 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'
                                                      : 'cursor-not-allowed text-gray-400 dark:text-gray-600'
                                            }`}
                                        >
                                            {entry.label}
                                            {!entry.available &&
                                                ' (unavailable)'}
                                        </button>
                                    ))}
                            </div>
                        </BiCard>
                    ))}
                </div>

                <div className="space-y-4 lg:col-span-3">
                    <BiCard title={currentEntry?.label ?? 'Report'}>
                        <form
                            onSubmit={applyDateFilter}
                            className="mb-4 flex flex-wrap items-end gap-3"
                        >
                            <div>
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400">
                                    From
                                </label>
                                <TextInput
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) =>
                                        setDateFrom(e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-500 dark:text-gray-400">
                                    To
                                </label>
                                <TextInput
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                />
                            </div>
                            <SecondaryButton type="submit">
                                Apply
                            </SecondaryButton>
                            <div className="ml-auto flex gap-2">
                                <a href={exportUrl('csv')}>
                                    <BiButton type="button" variant="secondary">
                                        Export CSV
                                    </BiButton>
                                </a>
                                <a href={exportUrl('pdf')}>
                                    <BiButton type="button">
                                        Export PDF
                                    </BiButton>
                                </a>
                            </div>
                        </form>

                        {report.summary && (
                            <div className="mb-4 flex flex-wrap gap-6 rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-800">
                                {Object.entries(report.summary).map(
                                    ([key, value]) => (
                                        <span key={key}>
                                            <span className="text-gray-500 dark:text-gray-400">
                                                {key.replace(/_/g, ' ')}:{' '}
                                            </span>
                                            <span className="font-semibold text-gray-900 dark:text-gray-100">
                                                {typeof value === 'number'
                                                    ? value.toLocaleString()
                                                    : value}
                                            </span>
                                        </span>
                                    ),
                                )}
                            </div>
                        )}

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        {report.columns.map((col) => (
                                            <th
                                                key={col}
                                                className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400"
                                            >
                                                {col}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {report.rows.map((row, i) => (
                                        <tr key={i}>
                                            {row.map((cell, j) => (
                                                <td
                                                    key={j}
                                                    className="px-3 py-2 text-sm text-gray-700 dark:text-gray-300"
                                                >
                                                    {cell ?? '—'}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                    {report.rows.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={report.columns.length}
                                                className="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No data for this report and date
                                                range.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </BiCard>
                </div>
            </div>
        </PlatformFinanceLayout>
    );
}
