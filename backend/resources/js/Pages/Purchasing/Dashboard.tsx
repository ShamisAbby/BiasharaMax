import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import StatCard from '@/Components/StatCard';
import PurchasingLayout from '@/Layouts/PurchasingLayout';
import { formatCurrency } from '@/lib/currency';
import {
    PurchasingDashboardSummary,
    PurchasingDashboardTrend,
    RecentDeliveryRow,
    RecentPurchaseOrderRow,
    SupplierLeadTime,
    TopSupplier,
} from '@/types/purchasing';
import {
    BanknotesIcon,
    CheckCircleIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    SparklesIcon,
    TruckIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

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

function sum(values: number[]): number {
    return values.reduce((total, value) => total + value, 0);
}

export default function PurchasingDashboard({
    summary,
    trend,
    topSuppliers,
    recentPurchaseOrders,
    recentDeliveries,
    supplierLeadTimes,
}: {
    summary: PurchasingDashboardSummary;
    trend: PurchasingDashboardTrend;
    topSuppliers: TopSupplier[];
    recentPurchaseOrders: RecentPurchaseOrderRow[];
    recentDeliveries: RecentDeliveryRow[];
    supplierLeadTimes: SupplierLeadTime[];
}) {
    const [poSearch, setPoSearch] = useState('');
    const [grnSearch, setGrnSearch] = useState('');

    const ordersThisWeek = sum(trend.orders);
    const valueThisWeek = sum(trend.value);

    const filteredOrders = useMemo(
        () =>
            recentPurchaseOrders.filter((order) =>
                `${order.po_number} ${order.supplier_name}`
                    .toLowerCase()
                    .includes(poSearch.toLowerCase()),
            ),
        [recentPurchaseOrders, poSearch],
    );

    const filteredDeliveries = useMemo(
        () =>
            recentDeliveries.filter((delivery) =>
                `${delivery.grn_number} ${delivery.po_number} ${delivery.supplier_name}`
                    .toLowerCase()
                    .includes(grnSearch.toLowerCase()),
            ),
        [recentDeliveries, grnSearch],
    );

    return (
        <PurchasingLayout title="Dashboard">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Purchasing
                </h1>
                <Link
                    href={route('purchasing.orders.create')}
                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    New Purchase Order
                </Link>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={<ClipboardDocumentListIcon className="h-5 w-5" />}
                    iconClassName="bg-indigo-600"
                    title="Total Purchase Orders"
                    value={summary.total_purchase_orders_count}
                    delta={`+${ordersThisWeek} this week`}
                    deltaTone="positive"
                    sparkline={trend.orders}
                />
                <StatCard
                    icon={<ClockIcon className="h-5 w-5" />}
                    iconClassName="bg-amber-600"
                    title="Pending Orders"
                    value={summary.pending_purchase_orders_count}
                    delta={
                        summary.pending_deliveries_count > 0
                            ? `${summary.pending_deliveries_count} awaiting delivery`
                            : undefined
                    }
                    deltaTone="warning"
                    sparkline={trend.orders}
                />
                <StatCard
                    icon={<CheckCircleIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="Completed Orders"
                    value={summary.completed_orders_count}
                    delta={`+${sum(trend.completed)} this week`}
                    deltaTone="positive"
                    sparkline={trend.completed}
                />
                <StatCard
                    icon={<BanknotesIcon className="h-5 w-5" />}
                    iconClassName="bg-cyan-600"
                    title="Total Order Value"
                    value={formatCurrency(summary.total_order_value)}
                    delta={`+${formatCurrency(valueThisWeek)} this week`}
                    deltaTone="positive"
                    sparkline={trend.value}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card
                    title="Purchase Orders"
                    actions={
                        <Link
                            href={route('purchasing.orders.index')}
                            className="text-sm font-medium text-indigo-600 hover:underline"
                        >
                            View all &rarr;
                        </Link>
                    }
                >
                    <input
                        type="search"
                        placeholder="Search PO number or supplier..."
                        value={poSearch}
                        onChange={(e) => setPoSearch(e.target.value)}
                        className="mb-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    />
                    {filteredOrders.length > 0 ? (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th className="pb-2 font-medium">
                                        PO Number
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Supplier
                                    </th>
                                    <th className="pb-2 font-medium">Total</th>
                                    <th className="pb-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {filteredOrders.map((order) => (
                                    <tr key={order.id}>
                                        <td className="py-2.5">
                                            <Link
                                                href={route(
                                                    'purchasing.orders.show',
                                                    order.id,
                                                )}
                                                className="font-medium text-indigo-600 hover:underline"
                                            >
                                                {order.po_number}
                                            </Link>
                                        </td>
                                        <td className="py-2.5 text-gray-600 dark:text-gray-300">
                                            {order.supplier_name}
                                        </td>
                                        <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                            {formatCurrency(order.total_amount)}
                                        </td>
                                        <td className="py-2.5">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANT[order.status]
                                                }
                                            >
                                                {order.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <p className="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            No purchase orders match your search.
                        </p>
                    )}
                </Card>

                <Card
                    title="Goods Received"
                    actions={
                        <Link
                            href={route('purchasing.goods-received.index')}
                            className="text-sm font-medium text-indigo-600 hover:underline"
                        >
                            View all &rarr;
                        </Link>
                    }
                >
                    <input
                        type="search"
                        placeholder="Search GRN, PO number or supplier..."
                        value={grnSearch}
                        onChange={(e) => setGrnSearch(e.target.value)}
                        className="mb-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    />
                    {filteredDeliveries.length > 0 ? (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th className="pb-2 font-medium">
                                        GRN Number
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Purchase Order
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Supplier
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Received
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {filteredDeliveries.map((delivery) => (
                                    <tr key={delivery.id}>
                                        <td className="py-2.5">
                                            <Link
                                                href={route(
                                                    'purchasing.goods-received.show',
                                                    delivery.id,
                                                )}
                                                className="font-medium text-indigo-600 hover:underline"
                                            >
                                                {delivery.grn_number}
                                            </Link>
                                        </td>
                                        <td className="py-2.5 text-gray-600 dark:text-gray-300">
                                            {delivery.po_number}
                                        </td>
                                        <td className="py-2.5 text-gray-600 dark:text-gray-300">
                                            {delivery.supplier_name}
                                        </td>
                                        <td className="py-2.5 text-gray-500 dark:text-gray-400">
                                            {new Date(
                                                delivery.received_at,
                                            ).toLocaleDateString()}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <p className="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            No deliveries match your search.
                        </p>
                    )}
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="Top Suppliers" description="By total spend">
                    {topSuppliers.length > 0 ? (
                        <div className="divide-y divide-gray-100 dark:divide-gray-800">
                            {topSuppliers.map((supplier) => (
                                <div
                                    key={supplier.supplier_id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <span className="text-gray-700 dark:text-gray-300">
                                        {supplier.name}
                                    </span>
                                    <span className="font-medium text-gray-900 dark:text-gray-100">
                                        {formatCurrency(supplier.total_spend)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No supplier spend recorded yet.
                        </p>
                    )}
                </Card>

                <Card
                    title="Supplier Lead Time"
                    description="Average days from sent to first delivery"
                >
                    {supplierLeadTimes.length > 0 ? (
                        <div className="divide-y divide-gray-100 dark:divide-gray-800">
                            {supplierLeadTimes.map((supplier) => (
                                <div
                                    key={supplier.supplier_id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <span className="text-gray-700 dark:text-gray-300">
                                        {supplier.name}
                                    </span>
                                    <span className="font-medium text-gray-900 dark:text-gray-100">
                                        {supplier.average_lead_time_days}d
                                        &middot; {supplier.completed_orders}{' '}
                                        orders
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No completed deliveries yet.
                        </p>
                    )}
                </Card>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 px-6 py-5 text-white">
                <div className="flex items-center gap-3">
                    <TruckIcon className="h-10 w-10 shrink-0 text-indigo-200" />
                    <div>
                        <p className="font-semibold">Keep suppliers reliable</p>
                        <p className="text-sm text-indigo-100">
                            {summary.active_suppliers_count} active supplier
                            {summary.active_suppliers_count === 1 ? '' : 's'} —
                            review lead times to catch slow deliveries early.
                        </p>
                    </div>
                </div>
                <Link
                    href={route('inventory.suppliers.index')}
                    className="flex items-center gap-1.5 rounded-md bg-white px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50"
                >
                    <SparklesIcon className="h-4 w-4" />
                    Manage Suppliers
                </Link>
            </div>
        </PurchasingLayout>
    );
}
