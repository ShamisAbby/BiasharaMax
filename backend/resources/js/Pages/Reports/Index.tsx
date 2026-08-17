import BiEmptyState from '@/Components/Bi/BiEmptyState';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    ArrowTrendingUpIcon,
    BanknotesIcon,
    BuildingStorefrontIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    ChartBarSquareIcon,
    ChartPieIcon,
    ClipboardDocumentCheckIcon,
    ClipboardDocumentListIcon,
    CpuChipIcon,
    CreditCardIcon,
    CubeIcon,
    CurrencyDollarIcon,
    DocumentChartBarIcon,
    ExclamationTriangleIcon,
    FunnelIcon,
    GlobeAltIcon,
    MagnifyingGlassIcon,
    ReceiptPercentIcon,
    ScaleIcon,
    ShoppingBagIcon,
    ShoppingCartIcon,
    SparklesIcon,
    SquaresPlusIcon,
    TagIcon,
    TruckIcon,
    UserGroupIcon,
    UsersIcon,
    WalletIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

interface ReportEntry {
    key: string;
    label: string;
    description: string;
    route?: string;
    action?: string;
    available: boolean;
}

interface HubStats {
    total_reports: number;
    available_reports: number;
    by_category: Record<string, { total: number; available: number }>;
}

interface Props {
    catalog: Record<string, ReportEntry[]>;
    hubStats: HubStats | null;
}

// Per-report icon mapping
const REPORT_ICONS: Record<string, React.ReactNode> = {
    'sales.daily': <CalendarDaysIcon className="h-5 w-5" />,
    'sales.monthly': <ChartBarIcon className="h-5 w-5" />,
    'sales.by_product': <TagIcon className="h-5 w-5" />,
    'sales.by_branch': <BuildingStorefrontIcon className="h-5 w-5" />,
    'sales.by_payment': <CreditCardIcon className="h-5 w-5" />,
    'sales.top_customers': <UserGroupIcon className="h-5 w-5" />,
    'sales.returns': <ArrowTrendingUpIcon className="h-5 w-5" />,
    'sales.slow_moving': <ExclamationTriangleIcon className="h-5 w-5" />,
    'sales.customer_debts': <WalletIcon className="h-5 w-5" />,
    'inventory.stock': <CubeIcon className="h-5 w-5" />,
    'inventory.low_stock': <ExclamationTriangleIcon className="h-5 w-5" />,
    'inventory.out_of_stock': <XMarkIcon className="h-5 w-5" />,
    'inventory.dead_stock': <ClipboardDocumentCheckIcon className="h-5 w-5" />,
    'inventory.expired': <CalendarDaysIcon className="h-5 w-5" />,
    'inventory.movements': <ArrowTrendingUpIcon className="h-5 w-5" />,
    'inventory.valuation': <CurrencyDollarIcon className="h-5 w-5" />,
    'purchasing.orders': <ShoppingCartIcon className="h-5 w-5" />,
    'purchasing.suppliers': <TruckIcon className="h-5 w-5" />,
    'purchasing.lead_times': <CalendarDaysIcon className="h-5 w-5" />,
    'purchasing.trend': <ChartBarIcon className="h-5 w-5" />,
    'finance.trial_balance': <ScaleIcon className="h-5 w-5" />,
    'finance.profit_loss': <ChartBarSquareIcon className="h-5 w-5" />,
    'finance.balance_sheet': <DocumentChartBarIcon className="h-5 w-5" />,
    'finance.cash_flow': <BanknotesIcon className="h-5 w-5" />,
    'finance.budget_actual': <FunnelIcon className="h-5 w-5" />,
    'finance.vat': <ReceiptPercentIcon className="h-5 w-5" />,
    'finance.income_tax': <ClipboardDocumentListIcon className="h-5 w-5" />,
    'finance.assets': <SquaresPlusIcon className="h-5 w-5" />,
    'finance.depreciation': <ArrowTrendingUpIcon className="h-5 w-5" />,
    'finance.payroll': <UsersIcon className="h-5 w-5" />,
    'finance.bank_recon': <CreditCardIcon className="h-5 w-5" />,
    'finance.pl_trend': <ChartPieIcon className="h-5 w-5" />,
    'crm.customers': <UserGroupIcon className="h-5 w-5" />,
    'crm.new_customers': <ArrowTrendingUpIcon className="h-5 w-5" />,
    'crm.top_customers': <SparklesIcon className="h-5 w-5" />,
    'crm.overdue': <WalletIcon className="h-5 w-5" />,
    'hr.payroll': <BanknotesIcon className="h-5 w-5" />,
    'hr.employees': <UsersIcon className="h-5 w-5" />,
    'hr.attendance': <CalendarDaysIcon className="h-5 w-5" />,
    'hr.leave': <GlobeAltIcon className="h-5 w-5" />,
    'ai.business_health': <SparklesIcon className="h-5 w-5" />,
    'ai.forecast': <ArrowTrendingUpIcon className="h-5 w-5" />,
    'ai.slow_movers': <ExclamationTriangleIcon className="h-5 w-5" />,
};

const CATEGORY_META: Record<
    string,
    { icon: React.ReactNode; color: string; bg: string; gradient: string }
> = {
    Sales: {
        icon: <ShoppingBagIcon className="h-4 w-4" />,
        color: 'text-blue-600 dark:text-blue-400',
        bg: 'bg-blue-100 dark:bg-blue-900/30',
        gradient: 'from-blue-500 to-blue-700',
    },
    Inventory: {
        icon: <CubeIcon className="h-4 w-4" />,
        color: 'text-emerald-600 dark:text-emerald-400',
        bg: 'bg-emerald-100 dark:bg-emerald-900/30',
        gradient: 'from-emerald-500 to-teal-600',
    },
    Purchasing: {
        icon: <ShoppingCartIcon className="h-4 w-4" />,
        color: 'text-orange-600 dark:text-orange-400',
        bg: 'bg-orange-100 dark:bg-orange-900/30',
        gradient: 'from-orange-500 to-amber-600',
    },
    Finance: {
        icon: <BanknotesIcon className="h-4 w-4" />,
        color: 'text-indigo-600 dark:text-indigo-400',
        bg: 'bg-indigo-100 dark:bg-indigo-900/30',
        gradient: 'from-indigo-500 to-violet-600',
    },
    CRM: {
        icon: <UserGroupIcon className="h-4 w-4" />,
        color: 'text-pink-600 dark:text-pink-400',
        bg: 'bg-pink-100 dark:bg-pink-900/30',
        gradient: 'from-pink-500 to-rose-600',
    },
    Employees: {
        icon: <UsersIcon className="h-4 w-4" />,
        color: 'text-teal-600 dark:text-teal-400',
        bg: 'bg-teal-100 dark:bg-teal-900/30',
        gradient: 'from-teal-500 to-cyan-600',
    },
    'AI Insights': {
        icon: <CpuChipIcon className="h-4 w-4" />,
        color: 'text-violet-600 dark:text-violet-400',
        bg: 'bg-violet-100 dark:bg-violet-900/30',
        gradient: 'from-violet-500 to-purple-700',
    },
};

export default function ReportsIndex({ catalog, hubStats }: Props) {
    const [search, setSearch] = useState('');
    const [activeCategory, setActiveCategory] = useState<string>('All');

    const categories = ['All', ...Object.keys(catalog)];

    const allReports = Object.entries(catalog).flatMap(([cat, reports]) =>
        reports.map((r) => ({ ...r, category: cat })),
    );

    const filtered = allReports.filter((r) => {
        const matchesCategory =
            activeCategory === 'All' || r.category === activeCategory;
        const matchesSearch =
            !search ||
            r.label.toLowerCase().includes(search.toLowerCase()) ||
            r.description.toLowerCase().includes(search.toLowerCase()) ||
            r.category.toLowerCase().includes(search.toLowerCase());
        return matchesCategory && matchesSearch;
    });

    const groupedFiltered: Record<
        string,
        (ReportEntry & { category: string })[]
    > = {};
    for (const r of filtered) {
        if (!groupedFiltered[r.category]) groupedFiltered[r.category] = [];
        groupedFiltered[r.category].push(r);
    }

    const getReportHref = (report: ReportEntry): string => {
        if (report.route) return route(report.route);
        return route('reports.show', { key: report.key });
    };

    return (
        <AuthenticatedLayout>
            <div className="py-8">
                {/* No max-width cap: these are data-dense grids and tables,
                    and every module layout runs full-bleed since the
                    dashboard did. Capping only here left wide empty gutters
                    on a large monitor and made the content jump width when
                    navigating in from a module page. */}
                <div className="mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Hero header */}
                    <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800 p-6 shadow-lg">
                        {/* Decorative circles */}
                        <div className="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/5" />
                        <div className="pointer-events-none absolute -bottom-10 -left-10 h-48 w-48 rounded-full bg-white/5" />

                        <div className="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <ChartBarSquareIcon className="h-6 w-6 text-indigo-300" />
                                    <span className="text-xs font-semibold uppercase tracking-widest text-indigo-300">
                                        BiasharaMax
                                    </span>
                                </div>
                                <h1 className="mt-1 text-2xl font-bold tracking-tight text-white">
                                    Business Intelligence &amp; Reports
                                </h1>
                                <p className="mt-1 text-sm text-indigo-200">
                                    All your reports in one place — analyse,
                                    export, and act on your data
                                </p>
                            </div>

                            {hubStats && (
                                <div className="flex shrink-0 gap-8 text-center">
                                    <div>
                                        <p className="text-4xl font-extrabold tabular-nums text-white">
                                            {hubStats.available_reports}
                                        </p>
                                        <p className="mt-0.5 text-xs font-medium text-indigo-300">
                                            Reports Available
                                        </p>
                                    </div>
                                    <div className="w-px bg-white/20" />
                                    <div>
                                        <p className="text-4xl font-extrabold tabular-nums text-white">
                                            {Object.keys(catalog).length}
                                        </p>
                                        <p className="mt-0.5 text-xs font-medium text-indigo-300">
                                            Categories
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Search */}
                        <div className="relative mt-4">
                            <MagnifyingGlassIcon className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-indigo-300" />
                            <input
                                type="text"
                                placeholder="Search reports by name, category or description…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full rounded-xl bg-white/10 py-3 pl-11 pr-4 text-sm text-white placeholder-indigo-300 backdrop-blur-sm transition focus:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30"
                            />
                            {search && (
                                <button
                                    onClick={() => setSearch('')}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-indigo-300 hover:text-white"
                                >
                                    <XMarkIcon className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Category cards */}
                    {hubStats && (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                            {Object.entries(hubStats.by_category).map(
                                ([cat, stats]) => {
                                    const meta = CATEGORY_META[cat];
                                    const isActive = activeCategory === cat;
                                    return (
                                        <button
                                            key={cat}
                                            onClick={() =>
                                                setActiveCategory(
                                                    isActive ? 'All' : cat,
                                                )
                                            }
                                            className={`group relative overflow-hidden rounded-xl p-3 text-left shadow-sm transition-all ${
                                                isActive
                                                    ? `bg-gradient-to-br ${meta?.gradient ?? 'from-indigo-500 to-violet-600'} text-white shadow-md ring-0`
                                                    : 'bg-white ring-1 ring-gray-200 hover:ring-indigo-300 dark:bg-gray-800 dark:ring-gray-700 dark:hover:ring-indigo-600'
                                            }`}
                                        >
                                            <div
                                                className={`mb-2 flex h-8 w-8 items-center justify-center rounded-lg ${
                                                    isActive
                                                        ? 'bg-white/20 text-white'
                                                        : `${meta?.bg ?? 'bg-gray-100'} ${meta?.color ?? 'text-gray-600'}`
                                                }`}
                                            >
                                                {meta?.icon ?? (
                                                    <ChartBarIcon className="h-4 w-4" />
                                                )}
                                            </div>
                                            <p
                                                className={`text-xs font-semibold leading-tight ${
                                                    isActive
                                                        ? 'text-white'
                                                        : 'text-gray-800 dark:text-gray-100'
                                                }`}
                                            >
                                                {cat}
                                            </p>
                                            <p
                                                className={`mt-0.5 text-xs ${
                                                    isActive
                                                        ? 'text-white/70'
                                                        : 'text-gray-400 dark:text-gray-500'
                                                }`}
                                            >
                                                {stats.available}/{stats.total}{' '}
                                                reports
                                            </p>
                                        </button>
                                    );
                                },
                            )}
                        </div>
                    )}

                    {/* Pill filter tabs */}
                    <div className="flex flex-wrap items-center gap-2">
                        {categories.map((cat) => (
                            <button
                                key={cat}
                                onClick={() => setActiveCategory(cat)}
                                className={`rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
                                    activeCategory === cat
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'bg-gray-100 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 dark:bg-gray-700/60 dark:text-gray-300 dark:hover:bg-gray-700'
                                }`}
                            >
                                {cat}
                                {cat !== 'All' && hubStats && (
                                    <span
                                        className={`ml-1.5 rounded-full px-1.5 py-0.5 text-xs ${
                                            activeCategory === cat
                                                ? 'bg-white/20 text-white'
                                                : 'bg-gray-200 text-gray-500 dark:bg-gray-600 dark:text-gray-300'
                                        }`}
                                    >
                                        {hubStats.by_category[cat]?.available ??
                                            0}
                                    </span>
                                )}
                            </button>
                        ))}
                        {search && (
                            <span className="ml-auto text-sm text-gray-500 dark:text-gray-400">
                                {filtered.length} result
                                {filtered.length !== 1 ? 's' : ''} for &ldquo;
                                {search}&rdquo;
                            </span>
                        )}
                    </div>

                    {/* Report grid — grouped by category */}
                    {filtered.length === 0 ? (
                        <BiEmptyState
                            title="No reports found"
                            description="Try a different search term or category filter."
                            icon={<ChartPieIcon className="h-10 w-10" />}
                        />
                    ) : (
                        <div className="space-y-8">
                            {Object.entries(groupedFiltered).map(
                                ([cat, reports]) => {
                                    const meta = CATEGORY_META[cat];
                                    return (
                                        <section key={cat}>
                                            {/* Category heading */}
                                            <div className="mb-3 flex items-center gap-3">
                                                <div
                                                    className={`flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br ${meta?.gradient ?? 'from-gray-500 to-gray-700'} text-white shadow-sm`}
                                                >
                                                    {meta?.icon ?? (
                                                        <ChartBarIcon className="h-4 w-4" />
                                                    )}
                                                </div>
                                                <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                    {cat}
                                                </h2>
                                                <div className="h-px flex-1 bg-gray-100 dark:bg-gray-700/60" />
                                                <span className="text-xs text-gray-400">
                                                    {
                                                        reports.filter(
                                                            (r) => r.available,
                                                        ).length
                                                    }{' '}
                                                    available
                                                </span>
                                            </div>

                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                                {reports.map((report) => (
                                                    <ReportCard
                                                        key={report.key}
                                                        report={report}
                                                        categoryMeta={meta}
                                                        href={getReportHref(
                                                            report,
                                                        )}
                                                    />
                                                ))}
                                            </div>
                                        </section>
                                    );
                                },
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function ReportCard({
    report,
    categoryMeta,
    href,
}: {
    report: ReportEntry & { category: string };
    categoryMeta?: {
        icon: React.ReactNode;
        color: string;
        bg: string;
        gradient: string;
    };
    href: string;
}) {
    const icon = REPORT_ICONS[report.key] ?? categoryMeta?.icon ?? (
        <ChartBarIcon className="h-5 w-5" />
    );

    if (!report.available) {
        return (
            <div className="flex cursor-not-allowed flex-col rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <div
                    className={`mb-3 flex h-9 w-9 items-center justify-center rounded-lg ${categoryMeta?.bg ?? 'bg-gray-100'} ${categoryMeta?.color ?? 'text-gray-400'} opacity-50`}
                >
                    {icon}
                </div>
                <p className="text-sm font-medium text-gray-400 dark:text-gray-500">
                    {report.label}
                </p>
                <p className="mt-0.5 text-xs text-gray-400/70 dark:text-gray-600">
                    {report.description}
                </p>
                <span className="mt-2 inline-flex w-fit items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-400 dark:bg-gray-700 dark:text-gray-500">
                    Coming soon
                </span>
            </div>
        );
    }

    return (
        <Link
            href={href}
            className="group relative flex flex-col overflow-hidden rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200/80 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-indigo-200 dark:bg-gray-800 dark:ring-gray-700 dark:hover:ring-indigo-700"
        >
            {/* Subtle top accent */}
            <div
                className={`absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r ${categoryMeta?.gradient ?? 'from-indigo-500 to-violet-600'} opacity-0 transition-opacity group-hover:opacity-100`}
            />

            <div
                className={`mb-3 flex h-9 w-9 items-center justify-center rounded-lg ${categoryMeta?.bg ?? 'bg-gray-100'} ${categoryMeta?.color ?? 'text-gray-600'} transition-colors group-hover:bg-gradient-to-br group-hover:${categoryMeta?.gradient} group-hover:text-white`}
            >
                {icon}
            </div>

            <p className="text-sm font-semibold text-gray-800 transition-colors group-hover:text-indigo-700 dark:text-gray-100 dark:group-hover:text-indigo-400">
                {report.label}
            </p>
            <p className="mt-1 flex-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                {report.description}
            </p>

            <div className="mt-3 flex items-center justify-between">
                <span
                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${categoryMeta?.bg ?? 'bg-gray-100'} ${categoryMeta?.color ?? 'text-gray-500'}`}
                >
                    {report.category}
                </span>
                <span className="text-xs font-medium text-indigo-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-indigo-400">
                    Open →
                </span>
            </div>
        </Link>
    );
}
