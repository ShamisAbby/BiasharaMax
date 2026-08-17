import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import SelectInput from '@/Components/SelectInput';
import StatCard from '@/Components/StatCard';
import SalesLayout from '@/Layouts/SalesLayout';
import { formatCurrency } from '@/lib/currency';
import {
    SaleReturn,
    SaleReturnDashboardSummary,
    SaleReturnStatus,
} from '@/types/sales';
import {
    ArrowUturnLeftIcon,
    BanknotesIcon,
    CheckCircleIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';
import { Link, router } from '@inertiajs/react';

const STATUS_VARIANT: Record<
    SaleReturnStatus,
    'warning' | 'success' | 'danger'
> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
};

export default function ReturnsIndex({
    returns,
    summary,
    reasonBreakdown,
    filters,
}: {
    returns: {
        data: SaleReturn[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    summary: SaleReturnDashboardSummary;
    reasonBreakdown: Array<{ reason: string; count: number }>;
    filters: { status?: string };
}) {
    const updateFilter = (status: string) => {
        router.get(
            route('sales.returns.index'),
            { status: status || undefined },
            { preserveState: true },
        );
    };

    return (
        <SalesLayout title="Returns">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={<ArrowUturnLeftIcon className="h-5 w-5" />}
                    iconClassName="bg-indigo-600"
                    title="Today's Returns"
                    value={summary.today_returns_count}
                    delta={`${formatCurrency(summary.today_return_value)} value`}
                />
                <StatCard
                    icon={<BanknotesIcon className="h-5 w-5" />}
                    iconClassName="bg-rose-600"
                    title="Refunds This Month"
                    value={formatCurrency(summary.refund_amount_this_month)}
                />
                <StatCard
                    icon={<ClockIcon className="h-5 w-5" />}
                    iconClassName="bg-amber-600"
                    title="Pending Returns"
                    value={summary.pending_returns_count}
                    deltaTone="warning"
                />
                <StatCard
                    icon={<CheckCircleIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="Approved Returns"
                    value={summary.approved_returns_count}
                />
            </div>

            {reasonBreakdown.length > 0 && (
                <Card title="Return Reasons">
                    <div className="flex flex-wrap gap-3">
                        {reasonBreakdown.map((row) => (
                            <Badge key={row.reason} variant="neutral">
                                {row.reason.replace('_', ' ')} · {row.count}
                            </Badge>
                        ))}
                    </div>
                </Card>
            )}

            <div className="flex items-center justify-between">
                <SelectInput
                    value={filters.status ?? ''}
                    onChange={(e) => updateFilter(e.target.value)}
                >
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </SelectInput>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Return',
                                'Sale',
                                'Customer',
                                'Reason',
                                'Refund',
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
                        {returns.data.map((r) => (
                            <tr
                                key={r.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {r.return_number}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {r.sale?.sale_number ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {r.customer?.name ?? 'Walk-in'}
                                </td>
                                <td className="px-4 py-3 text-sm capitalize text-gray-700 dark:text-gray-300">
                                    {r.reason.replace('_', ' ')}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {formatCurrency(r.refund_amount)}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge variant={STATUS_VARIANT[r.status]}>
                                        {r.status}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <Link
                                        href={route('sales.returns.show', r.id)}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {returns.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No return requests yet.
                    </p>
                )}

                {returns.meta.links.length > 3 && (
                    <div className="flex flex-wrap gap-1 border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                        {returns.meta.links.map((link, index) => (
                            <button
                                key={index}
                                disabled={!link.url}
                                onClick={() =>
                                    link.url &&
                                    router.get(
                                        link.url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                className={`rounded px-3 py-1 text-sm ${
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-gray-800'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </SalesLayout>
    );
}
