import Badge from '@/Components/Badge';
import { OPEN_BUSINESS_ASSISTANT_EVENT } from '@/Components/BusinessAssistant';
import Card from '@/Components/Card';
import SalesOverviewChart from '@/Components/Charts/SalesOverviewChart';
import StockHealthDonutChart from '@/Components/Charts/StockHealthDonutChart';
import StatCard from '@/Components/StatCard';
import { useCurrency } from '@/contexts/CurrencyContext';
import { useCountdown } from '@/hooks/useCountdown';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Business,
    BusinessHealth,
    BusinessPulse,
    PageProps,
    RecentActivityItem,
    Subscription,
} from '@/types';
import { FinancialSummary } from '@/types/accounting';
import { CrmDashboardSummary } from '@/types/crm';
import { InventoryDashboardSummary } from '@/types/inventory';
import { SalesDashboardSummary } from '@/types/sales';
import {
    ArchiveBoxIcon,
    BanknotesIcon,
    BuildingLibraryIcon,
    BuildingStorefrontIcon,
    CalculatorIcon,
    ClockIcon,
    CreditCardIcon,
    DocumentChartBarIcon,
    ExclamationTriangleIcon,
    PlusCircleIcon,
    ReceiptPercentIcon,
    ShieldCheckIcon,
    ShoppingCartIcon,
    SparklesIcon,
    UserGroupIcon,
    UserPlusIcon,
} from '@heroicons/react/24/outline';
import { Head, Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

interface SalesData {
    summary: SalesDashboardSummary;
    trend: Array<{ label: string; sales: number; profit: number }>;
    topProducts: Array<{
        product_name: string;
        quantity_sold: number;
        revenue: number;
    }>;
    paymentMethods: Array<{
        payment_method: string;
        total: number;
        count: number;
    }>;
    recentOrders: Array<{
        id: string;
        sale_number: string;
        total_amount: number;
        payment_status: 'paid' | 'partial' | 'unpaid';
        status: 'completed' | 'voided' | 'refunded';
        created_at: string;
    }>;
}

interface LowStockProduct {
    product_id: string;
    name: string;
    quantity: number;
    reorder_level: number;
}

interface BranchPerformanceRow {
    branch_id: string;
    name: string;
    revenue: number;
    percent_change: number | null;
}

interface PurchasingSummary {
    pending_purchase_orders_count: number;
    pending_deliveries_count: number;
    today_receipts_count: number;
    purchase_value_this_month: number;
    active_suppliers_count: number;
}

const HEALTH_VARIANT: Record<
    string,
    'success' | 'info' | 'warning' | 'danger'
> = {
    Excellent: 'success',
    Good: 'info',
    'Needs Attention': 'warning',
    Critical: 'danger',
};

const ORDER_STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral'
> = {
    paid: 'success',
    partial: 'warning',
    unpaid: 'warning',
    voided: 'danger',
};

function greeting(): string {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
}

export default function Dashboard({
    business,
    subscription,
    trialEndsAt,
    employeeCount,
    inventory,
    sales,
    financials,
    crm,
    businessHealth,
    businessPulse,
    recentActivity,
    lowStockProducts,
    branchPerformance,
    purchasing,
}: {
    business: Business | null;
    subscription: Subscription | null;
    trialEndsAt: string | null;
    employeeCount: number;
    inventory: InventoryDashboardSummary | null;
    sales: SalesData | null;
    financials: FinancialSummary | null;
    crm: CrmDashboardSummary | null;
    businessHealth: BusinessHealth | null;
    businessPulse: BusinessPulse | null;
    recentActivity: RecentActivityItem[];
    lowStockProducts: LowStockProduct[];
    branchPerformance: BranchPerformanceRow[];
    purchasing: PurchasingSummary | null;
}) {
    const trialCountdown = useCountdown(trialEndsAt);
    const { auth } = usePage<PageProps>().props;
    const firstName = auth.user?.name?.split(' ')[0] ?? 'there';

    const today = new Date().toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });

    const totalPaymentAmount =
        sales?.paymentMethods.reduce((sum, row) => sum + row.total, 0) ?? 0;
    const salesTrendSeries = sales?.trend.map((p) => p.sales) ?? [];
    const profitTrendSeries = sales?.trend.map((p) => p.profit) ?? [];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Dashboard
                        </h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {today}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <HeaderActionButton
                            href={route('inventory.products.create')}
                            icon={PlusCircleIcon}
                        >
                            Add Product
                        </HeaderActionButton>
                        <HeaderActionButton
                            href={route('pos.terminal')}
                            icon={ShoppingCartIcon}
                        >
                            New POS Sale
                        </HeaderActionButton>
                        <HeaderActionButton
                            href={route('settings.branches.index')}
                            icon={BuildingStorefrontIcon}
                        >
                            Add Branch
                        </HeaderActionButton>
                        <Link
                            href={route('finance.reports.profit-and-loss')}
                            className="flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            <DocumentChartBarIcon className="h-4 w-4" />
                            View P&amp;L Report
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-6 py-8">
                {/*
                  No max-width cap: this page is a grid of cards, not
                  prose, so it reads better using the full window than
                  boxed to 1280px with wide empty gutters on a large
                  monitor. `px-4` also gives it horizontal breathing room
                  on mobile, which the old `sm:px-6` alone did not.
                */}
                <div className="mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {greeting()}, {firstName} 👋
                        </h1>
                        <div className="flex flex-wrap items-center gap-2">
                            {businessHealth && (
                                <Badge
                                    variant={
                                        HEALTH_VARIANT[businessHealth.status]
                                    }
                                >
                                    Business {businessHealth.status} ·{' '}
                                    {businessHealth.score}%
                                </Badge>
                            )}
                            {subscription && (
                                <Badge
                                    variant={
                                        subscription.status === 'trialing'
                                            ? 'info'
                                            : 'success'
                                    }
                                >
                                    {subscription.plan?.name ?? 'No plan'} ·{' '}
                                    {subscription.status}
                                </Badge>
                            )}
                            {business?.business_type && (
                                <Badge variant="neutral">
                                    {business.business_type}
                                </Badge>
                            )}
                            <Badge variant="info">
                                {employeeCount} team member
                                {employeeCount === 1 ? '' : 's'}
                            </Badge>
                        </div>
                    </div>

                    {(() => {
                        const showTrial =
                            business?.status === 'trial' &&
                            trialCountdown &&
                            !trialCountdown.expired;
                        const showSummary =
                            businessHealth &&
                            businessHealth.recommendations.length > 0;

                        if (!showTrial && !showSummary) return null;

                        return (
                            <div className="space-y-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 dark:border-indigo-900/60 dark:bg-indigo-950/40">
                                {showTrial && trialCountdown && (
                                    <p className="text-sm text-indigo-800 dark:text-indigo-200">
                                        You're on a free trial &mdash;{' '}
                                        <strong className="font-mono tabular-nums">
                                            {String(
                                                trialCountdown.days,
                                            ).padStart(2, '0')}
                                            d{' '}
                                            {String(
                                                trialCountdown.hours,
                                            ).padStart(2, '0')}
                                            h{' '}
                                            {String(
                                                trialCountdown.minutes,
                                            ).padStart(2, '0')}
                                            m{' '}
                                            {String(
                                                trialCountdown.seconds,
                                            ).padStart(2, '0')}
                                            s
                                        </strong>{' '}
                                        remaining.{' '}
                                        <Link
                                            href={route(
                                                'settings.subscription.show',
                                            )}
                                            className="underline"
                                        >
                                            View plans
                                        </Link>
                                        .
                                    </p>
                                )}

                                {showTrial && showSummary && (
                                    <div className="border-t border-indigo-200/60 dark:border-indigo-800/60" />
                                )}

                                {showSummary && businessHealth && (
                                    <div>
                                        <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">
                                            <SparklesIcon className="h-4 w-4" />{' '}
                                            Today's Summary
                                        </p>
                                        <ul className="mt-1.5 space-y-1 text-sm text-indigo-900 dark:text-indigo-100">
                                            {businessHealth.recommendations
                                                .slice(0, 3)
                                                .map((rec) => (
                                                    <li key={rec}>{rec}</li>
                                                ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                        );
                    })()}

                    {/* Primary KPI row — 6 cards, matching the dashboard mockup */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        {sales && (
                            <SalesKpiCards
                                summary={sales.summary}
                                pulse={businessPulse}
                                salesTrend={salesTrendSeries}
                                profitTrend={profitTrendSeries}
                            />
                        )}

                        {inventory && (
                            <StatCard
                                icon={
                                    <ExclamationTriangleIcon className="h-5 w-5" />
                                }
                                iconClassName="bg-red-600"
                                title="Low Stock"
                                value={inventory.low_stock_count}
                                delta="View items"
                                deltaTone="warning"
                                href={route('inventory.products.index')}
                            />
                        )}
                        {sales && (
                            <StatCard
                                icon={<UserGroupIcon className="h-5 w-5" />}
                                iconClassName="bg-purple-600"
                                title="Customers"
                                value={sales.summary.customers_count}
                                href={route('sales.customers.index')}
                            />
                        )}
                        {purchasing && (
                            <StatCard
                                icon={
                                    <BuildingStorefrontIcon className="h-5 w-5" />
                                }
                                iconClassName="bg-cyan-600"
                                title="Pending Purchase Orders"
                                value={purchasing.pending_purchase_orders_count}
                                delta={
                                    purchasing.pending_deliveries_count > 0
                                        ? `${purchasing.pending_deliveries_count} awaiting delivery`
                                        : undefined
                                }
                                deltaTone="warning"
                                href={route('purchasing.orders.index')}
                            />
                        )}
                    </div>

                    {/* Sales Overview / Business Pulse / AI Assistant — three even columns */}
                    <div className="grid gap-6 lg:grid-cols-3">
                        {sales && (
                            <Card
                                title="Sales Overview"
                                actions={
                                    <span className="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-900/60 dark:text-gray-300">
                                        Last 14 days
                                    </span>
                                }
                            >
                                {sales.trend.some((p) => p.sales > 0) ? (
                                    <div className="h-64">
                                        <SalesOverviewChart
                                            data={sales.trend}
                                        />
                                    </div>
                                ) : (
                                    <p className="py-16 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No sales recorded yet —{' '}
                                        <Link
                                            href={route('pos.terminal')}
                                            className="text-indigo-600 hover:underline"
                                        >
                                            open the POS terminal
                                        </Link>{' '}
                                        to record your first sale.
                                    </p>
                                )}
                            </Card>
                        )}

                        {businessPulse && (
                            <BusinessPulseCompact pulse={businessPulse} />
                        )}

                        <AIAssistantPanel businessHealth={businessHealth} />
                    </div>

                    {/* Recent Sales / Low Stock Alert / Recent Activities — three even columns */}
                    <div className="grid gap-6 lg:grid-cols-3">
                        {sales && (
                            <RecentSalesCard
                                recentOrders={sales.recentOrders}
                            />
                        )}

                        <Card
                            title="Low Stock Alert"
                            actions={
                                <Link
                                    href={route('inventory.products.index')}
                                    className="text-sm font-medium text-indigo-600 hover:underline"
                                >
                                    Manage &rarr;
                                </Link>
                            }
                        >
                            {lowStockProducts.length > 0 ? (
                                <div className="space-y-4">
                                    {lowStockProducts.map((product) => {
                                        const ratio =
                                            product.reorder_level > 0
                                                ? Math.min(
                                                      100,
                                                      Math.round(
                                                          (product.quantity /
                                                              product.reorder_level) *
                                                              100,
                                                      ),
                                                  )
                                                : 0;

                                        return (
                                            <div key={product.product_id}>
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="font-medium text-gray-900 dark:text-gray-100">
                                                        {product.name}
                                                    </span>
                                                    <span className="text-gray-500 dark:text-gray-400">
                                                        Stock:{' '}
                                                        {product.quantity}
                                                    </span>
                                                </div>
                                                <div className="mt-1.5 h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                                    <div
                                                        className="h-1.5 rounded-full bg-red-500"
                                                        style={{
                                                            width: `${ratio}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Nothing low on stock right now.
                                </p>
                            )}
                        </Card>

                        <Card title="Recent Activities">
                            {recentActivity.length > 0 ? (
                                <div className="-mx-6 max-h-72 divide-y divide-gray-100 overflow-y-auto px-6 dark:divide-gray-800">
                                    {recentActivity.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-start gap-3 py-2.5 text-sm"
                                        >
                                            <ClockIcon className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                            <div>
                                                <p className="text-gray-700 dark:text-gray-300">
                                                    <span className="font-medium text-gray-900 dark:text-gray-100">
                                                        {item.actor_name}
                                                    </span>{' '}
                                                    {item.action}{' '}
                                                    {item.auditable_type ??
                                                        'a record'}
                                                </p>
                                                <p className="text-xs text-gray-400">
                                                    {new Date(
                                                        item.created_at,
                                                    ).toLocaleString()}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No activity yet.
                                </p>
                            )}
                        </Card>
                    </div>

                    {/* Financial overview */}
                    {(financials || inventory) && (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {financials && (
                                <FinancialKpiCards financials={financials} />
                            )}

                            {inventory && (
                                <StatCard
                                    icon={
                                        <ArchiveBoxIcon className="h-5 w-5" />
                                    }
                                    iconClassName="bg-teal-600"
                                    title="Inventory Value"
                                    value={inventory.inventory_value}
                                    href={route('inventory.dashboard')}
                                />
                            )}
                            {inventory && inventory.expiring_soon_count > 0 && (
                                <StatCard
                                    icon={<ClockIcon className="h-5 w-5" />}
                                    iconClassName="bg-orange-600"
                                    title="Expiring Products"
                                    value={inventory.expiring_soon_count}
                                    delta="Within 30 days"
                                    deltaTone="warning"
                                    href={route('inventory.products.index')}
                                />
                            )}
                        </div>
                    )}

                    {sales && (
                        <div className="grid gap-6 lg:grid-cols-3">
                            <Card title="Sales by payment method">
                                {sales.paymentMethods.length > 0 ? (
                                    <div className="space-y-3">
                                        {sales.paymentMethods.map((row) => {
                                            const percentage =
                                                totalPaymentAmount > 0
                                                    ? Math.round(
                                                          (row.total /
                                                              totalPaymentAmount) *
                                                              100,
                                                      )
                                                    : 0;

                                            return (
                                                <div key={row.payment_method}>
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span className="capitalize text-gray-700 dark:text-gray-300">
                                                            {row.payment_method.replace(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </span>
                                                        <OrderAmount
                                                            amount={row.total}
                                                        />
                                                    </div>
                                                    <div className="mt-1.5 h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                                        <div
                                                            className="h-1.5 rounded-full bg-indigo-500"
                                                            style={{
                                                                width: `${percentage}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No payments recorded yet.
                                    </p>
                                )}
                            </Card>

                            {branchPerformance.length > 0 ? (
                                <Card
                                    title="Branch performance"
                                    description="This month's revenue"
                                    className="lg:col-span-2"
                                >
                                    <div className="space-y-3">
                                        {branchPerformance.map((branch) => (
                                            <div
                                                key={branch.branch_id}
                                                className="flex items-center justify-between text-sm"
                                            >
                                                <span className="text-gray-700 dark:text-gray-300">
                                                    {branch.name}
                                                </span>
                                                <div className="flex items-center gap-2">
                                                    <OrderAmount
                                                        amount={branch.revenue}
                                                    />
                                                    {branch.percent_change !==
                                                        null && (
                                                        <span
                                                            className={
                                                                branch.percent_change >=
                                                                0
                                                                    ? 'text-emerald-600'
                                                                    : 'text-red-600'
                                                            }
                                                        >
                                                            {branch.percent_change >=
                                                            0
                                                                ? '↗'
                                                                : '↘'}{' '}
                                                            {Math.abs(
                                                                branch.percent_change,
                                                            )}
                                                            %
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </Card>
                            ) : (
                                <Card
                                    title="Top Selling Products"
                                    className="lg:col-span-2"
                                >
                                    {sales.topProducts.length > 0 ? (
                                        <div className="divide-y divide-gray-100 dark:divide-gray-800">
                                            {sales.topProducts.map(
                                                (product) => (
                                                    <div
                                                        key={
                                                            product.product_name
                                                        }
                                                        className="flex items-center justify-between py-2 text-sm"
                                                    >
                                                        <span className="text-gray-700 dark:text-gray-300">
                                                            {
                                                                product.product_name
                                                            }
                                                        </span>
                                                        <span className="font-medium text-gray-900 dark:text-gray-100">
                                                            {
                                                                product.quantity_sold
                                                            }
                                                        </span>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    ) : (
                                        <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No sales yet.
                                        </p>
                                    )}
                                </Card>
                            )}
                        </div>
                    )}

                    <div className="grid gap-6 sm:grid-cols-3">
                        <Card title="Business">
                            <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {business?.name ?? '—'}
                            </p>
                            <p className="mt-1 text-sm capitalize text-gray-500 dark:text-gray-400">
                                {business?.business_type}
                            </p>
                            {business?.slug && (
                                <a
                                    href={route(
                                        'public.website.show',
                                        business.slug,
                                    )}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-2 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    View your website &rarr;
                                </a>
                            )}
                        </Card>

                        <Card title="Subscription">
                            <Badge
                                variant={
                                    subscription?.status === 'trialing'
                                        ? 'info'
                                        : 'success'
                                }
                            >
                                {subscription?.status ?? 'none'}
                            </Badge>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {subscription?.plan?.name ?? 'No plan'}
                            </p>
                        </Card>

                        <Card title="Employees">
                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {employeeCount}
                            </p>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                team member{employeeCount === 1 ? '' : 's'}
                            </p>
                        </Card>
                    </div>

                    {businessHealth && (
                        <Card
                            title="Business Health"
                            description="Computed from real sales, inventory, debt and subscription signals"
                            actions={
                                <ShieldCheckIcon className="h-5 w-5 text-emerald-500" />
                            }
                        >
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full border-4 border-emerald-100 text-2xl font-bold text-gray-900 dark:border-emerald-900/40 dark:text-gray-100">
                                    {businessHealth.score}%
                                </div>
                                <div className="flex-1 space-y-1.5">
                                    {businessHealth.recommendations.map(
                                        (rec) => (
                                            <p
                                                key={rec}
                                                className="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400"
                                            >
                                                <SparklesIcon className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                                                {rec}
                                            </p>
                                        ),
                                    )}
                                </div>
                            </div>
                        </Card>
                    )}

                    {inventory && (
                        <Card title="Inventory Summary">
                            <div className="h-64">
                                <StockHealthDonutChart
                                    data={inventory.stock_health_breakdown}
                                />
                            </div>
                        </Card>
                    )}

                    <QuickActionsBar />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function HeaderActionButton({
    href,
    icon: Icon,
    children,
}: {
    href: string;
    icon: typeof PlusCircleIcon;
    children: ReactNode;
}) {
    return (
        <Link
            href={href}
            className="flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
        >
            <Icon className="h-4 w-4" />
            {children}
        </Link>
    );
}

/**
 * Rendered as a child of AuthenticatedLayout (not the page's top-level
 * function) since useCurrency()'s provider lives inside AuthenticatedLayout
 * — a hook called directly in the page's top-level function would run
 * before that provider mounts and would never see its context.
 */
function OrderAmount({ amount }: { amount: number }) {
    const { formatMoney } = useCurrency();

    return (
        <span className="font-medium text-gray-900 dark:text-gray-100">
            {formatMoney(amount)}
        </span>
    );
}

function SalesKpiCards({
    summary,
    pulse,
    salesTrend,
    profitTrend,
}: {
    summary: SalesDashboardSummary;
    pulse: BusinessPulse | null;
    salesTrend: number[];
    profitTrend: number[];
}) {
    const { formatMoney } = useCurrency();

    return (
        <>
            <StatCard
                icon={<ShoppingCartIcon className="h-5 w-5" />}
                iconClassName="bg-blue-600"
                title="Today's Sales"
                value={formatMoney(summary.today_revenue)}
                compareLabel={pulse ? 'vs last week' : undefined}
                delta={
                    pulse
                        ? pulsePercentLabel(pulse.revenue_growth.percent)
                        : `${summary.today_sales_count} sale${summary.today_sales_count === 1 ? '' : 's'}`
                }
                deltaTone={
                    pulse &&
                    pulse.revenue_growth.percent !== null &&
                    pulse.revenue_growth.percent < 0
                        ? 'negative'
                        : 'positive'
                }
                sparkline={salesTrend}
            />
            <StatCard
                icon={<BanknotesIcon className="h-5 w-5" />}
                iconClassName="bg-emerald-600"
                title="Today's Profit"
                value={formatMoney(summary.today_profit)}
                compareLabel={pulse ? 'vs last week' : undefined}
                delta={
                    pulse
                        ? pulsePercentLabel(pulse.profit_trend.percent)
                        : undefined
                }
                deltaTone={
                    pulse &&
                    pulse.profit_trend.percent !== null &&
                    pulse.profit_trend.percent < 0
                        ? 'negative'
                        : 'positive'
                }
                sparkline={profitTrend}
            />
            <StatCard
                icon={<CreditCardIcon className="h-5 w-5" />}
                iconClassName="bg-amber-600"
                title="Outstanding Debts"
                value={formatMoney(summary.outstanding_credit)}
                delta={
                    summary.unpaid_sales_count > 0
                        ? `${summary.unpaid_sales_count} unpaid`
                        : undefined
                }
                deltaTone="warning"
                href={route('sales.orders.index')}
            />
        </>
    );
}

/**
 * Same provider-ordering reason as SalesKpiCards above.
 */
function FinancialKpiCards({ financials }: { financials: FinancialSummary }) {
    const { formatMoney } = useCurrency();

    return (
        <>
            <StatCard
                icon={<BanknotesIcon className="h-5 w-5" />}
                iconClassName="bg-emerald-600"
                title="Cash in Hand"
                value={formatMoney(financials.cash_balance)}
                href={route('finance.dashboard')}
            />
            <StatCard
                icon={<BuildingLibraryIcon className="h-5 w-5" />}
                iconClassName="bg-indigo-600"
                title="Bank Balance"
                value={formatMoney(financials.bank_balance)}
                href={route('finance.dashboard')}
            />
            <StatCard
                icon={<ReceiptPercentIcon className="h-5 w-5" />}
                iconClassName="bg-rose-600"
                title="Today's Expenses"
                value={formatMoney(financials.today_expenses)}
                href={route('accounting.expenses.index')}
            />
            <StatCard
                icon={<ClockIcon className="h-5 w-5" />}
                iconClassName="bg-gray-600"
                title="Accounts Payable"
                value={formatMoney(financials.accounts_payable)}
                delta={
                    financials.pending_expenses_count > 0
                        ? `${financials.pending_expenses_count} pending approval`
                        : undefined
                }
                deltaTone="warning"
                href={route('accounting.expenses.index')}
            />
        </>
    );
}

function pulsePercentLabel(percent: number | null): string {
    if (percent === null) return '—';
    return `${percent >= 0 ? '+' : ''}${percent}%`;
}

/** Compact vertical Business Pulse list, sized to sit alongside the Sales Overview chart and AI panel. */
function BusinessPulseCompact({ pulse }: { pulse: BusinessPulse }) {
    const { formatMoney } = useCurrency();

    const factors: Array<{
        label: string;
        value: string;
        percent: number;
        tone: 'positive' | 'negative';
    }> = [
        {
            label: 'Revenue Growth',
            value: pulsePercentLabel(pulse.revenue_growth.percent),
            percent: Math.min(100, Math.abs(pulse.revenue_growth.percent ?? 0)),
            tone:
                pulse.revenue_growth.percent !== null &&
                pulse.revenue_growth.percent < 0
                    ? 'negative'
                    : 'positive',
        },
        {
            label: 'Profit Trend',
            value: pulsePercentLabel(pulse.profit_trend.percent),
            percent: Math.min(100, Math.abs(pulse.profit_trend.percent ?? 0)),
            tone:
                pulse.profit_trend.percent !== null &&
                pulse.profit_trend.percent < 0
                    ? 'negative'
                    : 'positive',
        },
        {
            label: 'Inventory Health',
            value: `${pulse.inventory_health.score}%`,
            percent: pulse.inventory_health.score,
            tone: pulse.inventory_health.score >= 70 ? 'positive' : 'negative',
        },
    ];

    if (pulse.cash_flow) {
        factors.push({
            label: 'Cash Flow',
            value: formatMoney(pulse.cash_flow.net_cash),
            percent: pulse.cash_flow.status === 'healthy' ? 100 : 35,
            tone:
                pulse.cash_flow.status === 'healthy' ? 'positive' : 'negative',
        });
    }

    return (
        <Card title="Business Pulse" description="Real-time signals">
            <div className="space-y-4">
                {factors.map((factor) => (
                    <div key={factor.label}>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-600 dark:text-gray-300">
                                {factor.label}
                            </span>
                            <span
                                className={`font-semibold ${factor.tone === 'positive' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}
                            >
                                {factor.value}
                            </span>
                        </div>
                        <div className="mt-1.5 h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                            <div
                                className={`h-1.5 rounded-full ${factor.tone === 'positive' ? 'bg-emerald-500' : 'bg-red-500'}`}
                                style={{
                                    width: `${Math.max(4, factor.percent)}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </div>
        </Card>
    );
}

/** Surfaces the same real recommendations shown in the trial banner, with a shortcut into the floating Business Assistant chat. */
function AIAssistantPanel({
    businessHealth,
}: {
    businessHealth: BusinessHealth | null;
}) {
    const recommendations = businessHealth?.recommendations.slice(0, 3) ?? [];

    return (
        <Card
            title="AI Business Assistant"
            actions={<Badge variant="info">Beta</Badge>}
        >
            <div className="space-y-3">
                {recommendations.length > 0 ? (
                    recommendations.map((rec) => (
                        <div
                            key={rec}
                            className="flex items-start gap-2 rounded-lg bg-indigo-50 p-3 text-sm text-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-100"
                        >
                            <SparklesIcon className="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" />
                            {rec}
                        </div>
                    ))
                ) : (
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        No recommendations right now — your business signals
                        look steady.
                    </p>
                )}

                <button
                    type="button"
                    onClick={() =>
                        window.dispatchEvent(
                            new Event(OPEN_BUSINESS_ASSISTANT_EVENT),
                        )
                    }
                    className="flex w-full items-center justify-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    <SparklesIcon className="h-4 w-4" />
                    Ask AI Assistant
                </button>
            </div>
        </Card>
    );
}

function RecentSalesCard({
    recentOrders,
}: {
    recentOrders: SalesData['recentOrders'];
}) {
    return (
        <Card
            title="Recent Sales"
            actions={
                <Link
                    href={route('sales.orders.index')}
                    className="text-sm font-medium text-indigo-600 hover:underline"
                >
                    View All &rarr;
                </Link>
            }
        >
            {recentOrders.length > 0 ? (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-gray-400">
                            <th className="pb-2 font-medium">Order</th>
                            <th className="pb-2 font-medium">Amount</th>
                            <th className="pb-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                        {recentOrders.map((order) => (
                            <tr key={order.id}>
                                <td className="py-2.5">
                                    <Link
                                        href={route(
                                            'sales.orders.show',
                                            order.id,
                                        )}
                                        className="font-medium text-indigo-600 hover:underline"
                                    >
                                        #{order.sale_number}
                                    </Link>
                                </td>
                                <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                    <OrderAmount amount={order.total_amount} />
                                </td>
                                <td className="py-2.5">
                                    <Badge
                                        variant={
                                            ORDER_STATUS_VARIANT[
                                                order.status === 'voided'
                                                    ? 'voided'
                                                    : order.payment_status
                                            ]
                                        }
                                    >
                                        {order.status === 'voided'
                                            ? 'Voided'
                                            : order.payment_status}
                                    </Badge>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                    No orders yet.
                </p>
            )}
        </Card>
    );
}

const QUICK_ACTIONS: Array<{
    label: string;
    route: string;
    icon: typeof PlusCircleIcon;
    className: string;
}> = [
    {
        label: 'Add Product',
        route: 'inventory.products.create',
        icon: PlusCircleIcon,
        className: 'bg-indigo-600 hover:bg-indigo-700',
    },
    {
        label: 'New POS Sale',
        route: 'pos.terminal',
        icon: ShoppingCartIcon,
        className: 'bg-blue-600 hover:bg-blue-700',
    },
    {
        label: 'Add Branch',
        route: 'settings.branches.index',
        icon: BuildingStorefrontIcon,
        className: 'bg-cyan-600 hover:bg-cyan-700',
    },
    {
        label: 'Add Customer',
        route: 'sales.customers.index',
        icon: UserPlusIcon,
        className: 'bg-purple-600 hover:bg-purple-700',
    },
    {
        label: 'Add Supplier',
        route: 'inventory.suppliers.index',
        icon: BuildingStorefrontIcon,
        className: 'bg-teal-600 hover:bg-teal-700',
    },
    {
        label: 'Add Expense',
        route: 'accounting.expenses.index',
        icon: CalculatorIcon,
        className: 'bg-rose-600 hover:bg-rose-700',
    },
    {
        label: 'Add Income',
        route: 'accounting.income.index',
        icon: BanknotesIcon,
        className: 'bg-emerald-600 hover:bg-emerald-700',
    },
    {
        label: 'P&L Report',
        route: 'finance.reports.profit-and-loss',
        icon: DocumentChartBarIcon,
        className: 'bg-amber-600 hover:bg-amber-700',
    },
];

function QuickActionsBar() {
    return (
        <Card title="Quick Actions">
            <div className="flex flex-wrap gap-3">
                {QUICK_ACTIONS.map((action) => {
                    const Icon = action.icon;

                    return (
                        <Link
                            key={action.route}
                            href={route(action.route)}
                            className={`flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium text-white transition ${action.className}`}
                        >
                            <Icon className="h-4 w-4" />
                            {action.label}
                        </Link>
                    );
                })}
                <button
                    type="button"
                    onClick={() =>
                        window.dispatchEvent(
                            new Event(OPEN_BUSINESS_ASSISTANT_EVENT),
                        )
                    }
                    className="flex items-center gap-1.5 rounded-full bg-gray-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800"
                >
                    <SparklesIcon className="h-4 w-4" />
                    Ask AI Assistant
                </button>
            </div>
        </Card>
    );
}
