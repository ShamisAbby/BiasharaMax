import Badge from '@/Components/Badge';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PurchasingLayout from '@/Layouts/PurchasingLayout';
import { formatCurrency } from '@/lib/currency';
import { PurchaseOrder } from '@/types/purchasing';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Option = { id: string; name: string };

const STATUS_VARIANT: Record<
    string,
    'neutral' | 'success' | 'warning' | 'danger' | 'info'
> = {
    draft: 'neutral',
    pending_approval: 'warning',
    approved: 'info',
    rejected: 'danger',
    sent: 'info',
    partially_received: 'warning',
    fully_received: 'success',
    cancelled: 'danger',
    closed: 'neutral',
};

export default function OrdersIndex({
    orders,
    suppliers,
    filters,
}: {
    orders: {
        data: PurchaseOrder[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    suppliers: Option[];
    filters: { search?: string; status?: string; supplier_id?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('purchasing.orders.index'),
            { ...filters, search },
            { preserveState: true },
        );
    };

    const updateFilter = (key: string, value: string) => {
        router.get(
            route('purchasing.orders.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true },
        );
    };

    return (
        <PurchasingLayout title="Purchase Orders">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={submitSearch} className="flex gap-2">
                    <TextInput
                        placeholder="Search PO number..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-56"
                    />
                    <SelectInput
                        value={filters.status ?? ''}
                        onChange={(e) => updateFilter('status', e.target.value)}
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="pending_approval">
                            Pending Approval
                        </option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="sent">Sent</option>
                        <option value="partially_received">
                            Partially Received
                        </option>
                        <option value="fully_received">Fully Received</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="closed">Closed</option>
                    </SelectInput>
                    <SelectInput
                        value={filters.supplier_id ?? ''}
                        onChange={(e) =>
                            updateFilter('supplier_id', e.target.value)
                        }
                    >
                        <option value="">All suppliers</option>
                        {suppliers.map((supplier) => (
                            <option key={supplier.id} value={supplier.id}>
                                {supplier.name}
                            </option>
                        ))}
                    </SelectInput>
                </form>
                <Link href={route('purchasing.orders.create')}>
                    <PrimaryButton>New Purchase Order</PrimaryButton>
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'PO Number',
                                'Supplier',
                                'Order Date',
                                'Total',
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
                        {orders.data.map((order) => (
                            <tr
                                key={order.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {order.po_number}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {order.supplier?.name ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {order.order_date}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {formatCurrency(order.total_amount)}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={STATUS_VARIANT[order.status]}
                                    >
                                        {order.status.replace('_', ' ')}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <Link
                                        href={route(
                                            'purchasing.orders.show',
                                            order.id,
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

                {orders.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No purchase orders yet. Create one to start ordering
                        from a supplier.
                    </p>
                )}

                {orders.meta.links.length > 3 && (
                    <div className="flex flex-wrap gap-1 border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                        {orders.meta.links.map((link, index) => (
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
        </PurchasingLayout>
    );
}
