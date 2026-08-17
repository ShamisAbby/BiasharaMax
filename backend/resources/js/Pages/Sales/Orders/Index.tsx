import Badge from '@/Components/Badge';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import SalesLayout from '@/Layouts/SalesLayout';
import { formatCurrency } from '@/lib/currency';
import { Sale } from '@/types/sales';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const STATUS_VARIANT = {
    completed: 'success',
    voided: 'neutral',
    refunded: 'warning',
} as const;

const PAYMENT_STATUS_VARIANT = {
    paid: 'success',
    partial: 'warning',
    unpaid: 'danger',
} as const;

export default function SalesOrdersIndex({
    sales,
    filters,
}: {
    sales: {
        data: Sale[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    filters: {
        search?: string;
        status?: string;
        payment_status?: string;
        source?: string;
    };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('sales.orders.index'),
            { ...filters, search },
            { preserveState: true },
        );
    };

    const applyFilter = (key: string, value: string) => {
        router.get(
            route('sales.orders.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true },
        );
    };

    return (
        <SalesLayout title="Orders">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={submitSearch} className="flex gap-2">
                    <TextInput
                        placeholder="Search by sale # or customer..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-64"
                    />
                </form>

                <div className="flex gap-2">
                    <SelectInput
                        value={filters.status ?? ''}
                        onChange={(e) => applyFilter('status', e.target.value)}
                    >
                        <option value="">All statuses</option>
                        <option value="completed">Completed</option>
                        <option value="voided">Voided</option>
                    </SelectInput>
                    <SelectInput
                        value={filters.payment_status ?? ''}
                        onChange={(e) =>
                            applyFilter('payment_status', e.target.value)
                        }
                    >
                        <option value="">All payment statuses</option>
                        <option value="paid">Paid</option>
                        <option value="partial">Partial</option>
                        <option value="unpaid">Unpaid</option>
                    </SelectInput>
                    <SelectInput
                        value={filters.source ?? ''}
                        onChange={(e) => applyFilter('source', e.target.value)}
                    >
                        <option value="">All channels</option>
                        <option value="pos">In-Store (POS)</option>
                        <option value="online">Online</option>
                    </SelectInput>
                </div>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Sale #',
                                'Channel',
                                'Customer',
                                'Items',
                                'Total',
                                'Payment',
                                'Status',
                                'Date',
                            ].map((header) => (
                                <th
                                    key={header}
                                    className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {sales.data.map((sale) => (
                            <tr
                                key={sale.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm">
                                    <Link
                                        href={route(
                                            'sales.orders.show',
                                            sale.id,
                                        )}
                                        className="font-medium text-indigo-600 hover:underline"
                                    >
                                        {sale.sale_number}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={
                                            sale.source === 'online'
                                                ? 'info'
                                                : 'neutral'
                                        }
                                    >
                                        {sale.source === 'online'
                                            ? 'Online'
                                            : 'POS'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {sale.customer?.name ?? 'Walk-in'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {sale.items_count}
                                </td>
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {formatCurrency(sale.total_amount)}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={
                                            PAYMENT_STATUS_VARIANT[
                                                sale.payment_status
                                            ]
                                        }
                                    >
                                        {sale.payment_status}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={STATUS_VARIANT[sale.status]}
                                    >
                                        {sale.status}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {new Date(sale.created_at).toLocaleString()}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {sales.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No sales yet.{' '}
                        <Link
                            href={route('pos.terminal')}
                            className="text-indigo-600 hover:underline"
                        >
                            Open the POS terminal
                        </Link>{' '}
                        to record your first sale.
                    </p>
                )}

                {sales.meta.links.length > 3 && (
                    <div className="flex justify-center gap-1 border-t border-gray-100 px-6 py-4 dark:border-gray-700">
                        {sales.meta.links.map((link, index) => (
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
                                className={`rounded-md px-3 py-1 text-sm ${
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-gray-700'
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
