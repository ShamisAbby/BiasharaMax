import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import StockHealthDonutChart from '@/Components/Charts/StockHealthDonutChart';
import StockMovementTrendChart from '@/Components/Charts/StockMovementTrendChart';
import InventoryLayout from '@/Layouts/InventoryLayout';
import { formatCurrency } from '@/lib/currency';
import { InventoryDashboardSummary } from '@/types/inventory';
import { Link } from '@inertiajs/react';

export default function InventoryDashboard(summary: InventoryDashboardSummary) {
    const healthVariant =
        summary.health_score >= 80
            ? 'success'
            : summary.health_score >= 50
              ? 'warning'
              : 'danger';

    return (
        <InventoryLayout title="Dashboard">
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <Card title="Total Products">
                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {summary.total_products}
                    </p>
                </Card>
                <Card title="Inventory Value">
                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {formatCurrency(summary.inventory_value)}
                    </p>
                </Card>
                <Card title="Low Stock">
                    <p className="text-2xl font-bold text-orange-600">
                        {summary.low_stock_count}
                    </p>
                </Card>
                <Card title="Out of Stock">
                    <p className="text-2xl font-bold text-red-600">
                        {summary.out_of_stock_count}
                    </p>
                </Card>
                <Card title="Expiring Soon (30 days)">
                    <p className="text-2xl font-bold text-orange-600">
                        {summary.expiring_soon_count}
                    </p>
                </Card>
                <Card title="Expired">
                    <p className="text-2xl font-bold text-red-600">
                        {summary.expired_count}
                    </p>
                </Card>
                <Card title="Today's Stock Movements">
                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {summary.today_stock_movements}
                    </p>
                </Card>
                <Card title="Inventory Health Score">
                    <div className="flex items-center gap-2">
                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {summary.health_score}
                        </p>
                        <Badge variant={healthVariant}>
                            {summary.health_score >= 80
                                ? 'Healthy'
                                : summary.health_score >= 50
                                  ? 'Needs attention'
                                  : 'Critical'}
                        </Badge>
                    </div>
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card
                    title="Stock movement (last 14 days)"
                    className="lg:col-span-2"
                >
                    <div className="h-72">
                        <StockMovementTrendChart
                            data={summary.stock_movement_trend}
                        />
                    </div>
                </Card>
                <Card title="Stock health">
                    <div className="h-72">
                        <StockHealthDonutChart
                            data={summary.stock_health_breakdown}
                        />
                    </div>
                </Card>
            </div>

            <Card title="Recently added products">
                {summary.recent_products.length > 0 ? (
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {summary.recent_products.map((product) => (
                            <div
                                key={product.id}
                                className="flex items-center justify-between py-2"
                            >
                                <Link
                                    href={route(
                                        'inventory.products.show',
                                        product.id,
                                    )}
                                    className="text-sm font-medium text-indigo-600 hover:underline"
                                >
                                    {product.name}
                                </Link>
                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                    {product.sku} &middot;{' '}
                                    {formatCurrency(product.selling_price)}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-gray-500">
                        No products yet.{' '}
                        <Link
                            href={route('inventory.products.create')}
                            className="text-indigo-600 hover:underline"
                        >
                            Add your first product
                        </Link>
                        .
                    </p>
                )}
            </Card>
        </InventoryLayout>
    );
}
