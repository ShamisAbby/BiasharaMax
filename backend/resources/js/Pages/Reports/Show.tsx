import BiCard from '@/Components/Bi/BiCard';
import BiChart from '@/Components/Bi/BiChart';
import BiEmptyState from '@/Components/Bi/BiEmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import {
    ArrowLeftIcon,
    ChartBarIcon,
    TableCellsIcon,
} from '@heroicons/react/24/outline';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface ReportInfo {
    key: string;
    label: string;
    description: string;
    category: string;
    route?: string;
    action?: string;
    available: boolean;
}

interface ReportData {
    columns: string[];
    rows: (string | number | null)[][];
    summary: Record<string, unknown>;
    chart_type?: 'bar' | 'line' | 'doughnut';
}

interface Props {
    report: ReportInfo;
    data: ReportData;
    filters: { date_from: string; date_to: string };
}

const CURRENCY_COLUMNS = [
    'Revenue',
    'Amount',
    'Profit',
    'Cost',
    'Value',
    'Gross',
    'Net',
    'Deductions',
    'Balance',
    'Outstanding Balance',
    'Credit Limit',
    'Total Refunded',
    'Refund Amount',
    'Depreciation',
    'Acc. Depreciation',
    'Book Value',
    'Avg Cost',
    'Total Value',
    'Total Purchases',
    'Outstanding',
];

function isCurrencyCol(col: string): boolean {
    return CURRENCY_COLUMNS.some((c) => col.includes(c));
}

function formatCell(value: string | number | null, col: string): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'number' && isCurrencyCol(col))
        return formatCurrency(value);
    return String(value);
}

export default function ReportShow({ report, data, filters }: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [view, setView] = useState<'table' | 'chart'>('table');

    const hasDateFilter = !!report.action;
    const hasChart = data.rows.length > 0 && data.columns.length >= 2;
    const chartType = data.chart_type ?? 'bar';

    const chartLabels = data.rows.map((r) => String(r[0] ?? ''));
    const chartDatasets = data.columns.slice(1).map((col, idx) => ({
        label: col,
        data: data.rows.map((r) => Number(r[idx + 1] ?? 0)),
        color: ['#4F46E5', '#10B981', '#F97316', '#EC4899', '#06B6D4'][idx % 5],
    }));

    function applyFilters() {
        router.get(route('reports.show', { key: report.key }), {
            date_from: dateFrom,
            date_to: dateTo,
        });
    }

    return (
        <AuthenticatedLayout>
            <div className="py-8">
                {/* No max-width cap — see Reports/Index. */}
                <div className="mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Breadcrumb */}
                    <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <Link
                            href={route('reports.index')}
                            className="flex items-center gap-1 hover:text-indigo-600"
                        >
                            <ArrowLeftIcon className="h-4 w-4" />
                            Reports Center
                        </Link>
                        <span>/</span>
                        <span className="text-gray-700 dark:text-gray-300">
                            {report.category}
                        </span>
                        <span>/</span>
                        <span className="font-medium text-gray-900 dark:text-white">
                            {report.label}
                        </span>
                    </div>

                    {/* Header bar */}
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-xl font-bold text-gray-900 dark:text-white">
                                {report.label}
                            </h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {report.description}
                            </p>
                        </div>

                        {/* Controls */}
                        <div className="flex flex-wrap items-center gap-2">
                            {hasDateFilter && (
                                <>
                                    <input
                                        type="date"
                                        value={dateFrom}
                                        onChange={(e) =>
                                            setDateFrom(e.target.value)
                                        }
                                        className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                    <span className="text-sm text-gray-400">
                                        to
                                    </span>
                                    <input
                                        type="date"
                                        value={dateTo}
                                        onChange={(e) =>
                                            setDateTo(e.target.value)
                                        }
                                        className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                    <button
                                        onClick={applyFilters}
                                        className="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                                    >
                                        Apply
                                    </button>
                                </>
                            )}
                            {hasChart && (
                                <div className="flex rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button
                                        onClick={() => setView('table')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 text-sm transition-colors ${
                                            view === 'table'
                                                ? 'bg-indigo-600 text-white'
                                                : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'
                                        } rounded-l-lg`}
                                    >
                                        <TableCellsIcon className="h-4 w-4" />
                                        Table
                                    </button>
                                    <button
                                        onClick={() => setView('chart')}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 text-sm transition-colors ${
                                            view === 'chart'
                                                ? 'bg-indigo-600 text-white'
                                                : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'
                                        } rounded-r-lg`}
                                    >
                                        <ChartBarIcon className="h-4 w-4" />
                                        Chart
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Summary cards */}
                    {Object.keys(data.summary).length > 0 && (
                        <div className="flex flex-wrap gap-3">
                            {Object.entries(data.summary).map(([k, v]) => {
                                if (Array.isArray(v)) return null;
                                const label = k
                                    .replace(/_/g, ' ')
                                    .replace(/\b\w/g, (c) => c.toUpperCase());
                                const display =
                                    typeof v === 'number' && k.includes('total')
                                        ? formatCurrency(v as number)
                                        : String(v);
                                return (
                                    <div
                                        key={k}
                                        className="rounded-xl bg-white px-5 py-3 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                                    >
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            {label}
                                        </p>
                                        <p className="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">
                                            {display}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {/* AI recommendations if present */}
                    {Array.isArray(data.summary.recommendations) &&
                        (data.summary.recommendations as string[]).length >
                            0 && (
                            <BiCard title="AI Recommendations">
                                <ul className="space-y-2">
                                    {(
                                        data.summary.recommendations as string[]
                                    ).map((rec, i) => (
                                        <li
                                            key={i}
                                            className="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                                {i + 1}
                                            </span>
                                            {rec}
                                        </li>
                                    ))}
                                </ul>
                            </BiCard>
                        )}

                    {/* Main content */}
                    {data.rows.length === 0 ? (
                        <BiEmptyState
                            title="No data for this period"
                            description="Try expanding your date range or check back once you have more transactions."
                            icon={<ChartBarIcon className="h-10 w-10" />}
                        />
                    ) : view === 'chart' && hasChart ? (
                        <BiCard title={report.label}>
                            <BiChart
                                type={chartType}
                                height={320}
                                labels={chartLabels}
                                datasets={chartDatasets}
                                showLegend
                            />
                        </BiCard>
                    ) : (
                        <BiCard>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            {data.columns.map((col) => (
                                                <th
                                                    key={col}
                                                    className="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                                >
                                                    {col}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {data.rows.map((row, rowIdx) => (
                                            <tr
                                                key={rowIdx}
                                                className="dark:hover:bg-gray-750 transition-colors hover:bg-gray-50"
                                            >
                                                {row.map((cell, cellIdx) => (
                                                    <td
                                                        key={cellIdx}
                                                        className={`whitespace-nowrap px-4 py-2.5 text-gray-700 dark:text-gray-300 ${
                                                            cellIdx === 0
                                                                ? 'font-medium'
                                                                : ''
                                                        } ${
                                                            typeof cell ===
                                                                'number' &&
                                                            isCurrencyCol(
                                                                data.columns[
                                                                    cellIdx
                                                                ] ?? '',
                                                            )
                                                                ? 'text-right tabular-nums'
                                                                : ''
                                                        }`}
                                                    >
                                                        {formatCell(
                                                            cell,
                                                            data.columns[
                                                                cellIdx
                                                            ] ?? '',
                                                        )}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <p className="mt-3 px-4 pb-4 text-xs text-gray-400 dark:text-gray-500">
                                {data.rows.length} row
                                {data.rows.length !== 1 ? 's' : ''}
                            </p>
                        </BiCard>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
