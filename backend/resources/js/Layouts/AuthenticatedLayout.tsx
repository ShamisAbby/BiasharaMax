import ApplicationLogo from '@/Components/ApplicationLogo';
import BusinessAssistant, {
    OPEN_BUSINESS_ASSISTANT_EVENT,
} from '@/Components/BusinessAssistant';
import Dropdown from '@/Components/Dropdown';
import ErrorBoundary from '@/Components/ErrorBoundary';
import GlobalSearch from '@/Components/GlobalSearch';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import NotificationBell from '@/Components/NotificationBell';
import SubscriptionCard from '@/Components/SubscriptionCard';
import { CurrencyProvider, useCurrency } from '@/contexts/CurrencyContext';
import { useLocale } from '@/contexts/LocaleContext';
import { useCollapsedNavGroups } from '@/hooks/useCollapsedNavGroups';
import { useDarkMode } from '@/hooks/useDarkMode';
import { useFlashToasts } from '@/hooks/useFlashToasts';
import { useRecentlyVisited } from '@/hooks/useRecentlyVisited';
import { useSidebarFavorites } from '@/hooks/useSidebarFavorites';
import { useSignOutConfirm } from '@/hooks/useSignOutConfirm';
import { safeRoute } from '@/lib/safeRoute';
import { Subscription } from '@/types';
import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import {
    BanknotesIcon,
    Bars3Icon,
    BuildingOffice2Icon,
    BuildingStorefrontIcon,
    CalculatorIcon,
    ChartBarIcon,
    CheckIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ClockIcon,
    Cog6ToothIcon,
    CreditCardIcon,
    CubeIcon,
    CurrencyDollarIcon,
    GlobeAltIcon,
    LifebuoyIcon,
    MoonIcon,
    PlusIcon,
    ShoppingCartIcon,
    SparklesIcon,
    Squares2X2Icon,
    StarIcon as StarOutlineIcon,
    SunIcon,
    TruckIcon,
    UserGroupIcon,
    UserPlusIcon,
    UsersIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { StarIcon as StarSolidIcon } from '@heroicons/react/24/solid';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Fragment,
    PropsWithChildren,
    ReactNode,
    useMemo,
    useState,
} from 'react';

/** A single sidebar destination — either real (has a route) or not yet built. */
type NavLeaf = {
    key: string;
    label: string;
    route?: string;
    activePattern?: string;
    soon?: boolean;
    /**
     * The dashboard module this entry belongs to. A section the Super
     * Admin has switched off for this business is hidden entirely — the
     * routes 404, so advertising them would only produce dead links.
     */
    module?: string;
    /**
     * Permission slugs, any one of which reveals this entry. Omitted means
     * the destination is genuinely open to every employee — the dashboard,
     * your own time clock, your own leave.
     *
     * These mirror what the controller or policy behind each route actually
     * checks. The sidebar previously rendered all 47 destinations to
     * everyone, so a cashier was shown Journal Entries, Payroll and Roles &
     * Permissions and got a 403 on each.
     */
    permission?: string[];
};

/** A top-level sidebar entry: a direct link, an expandable group of leaves, or fully "soon". */
type NavSection = {
    key: string;
    icon: typeof Squares2X2Icon;
    route?: string;
    activePattern?: string;
    soon?: boolean;
    module?: string;
    permission?: string[];
    children?: NavLeaf[];
};

const NAV_SECTIONS: NavSection[] = [
    { key: 'nav.dashboard', route: 'dashboard', icon: Squares2X2Icon },
    {
        key: 'nav.business',
        module: 'business',
        icon: BuildingOffice2Icon,
        children: [
            // No permission: every employee can see which business they
            // belong to. The edit form itself is what checks.
            {
                key: 'business.profile',
                label: 'Business Profile',
                route: 'settings.business.edit',
            },
            {
                key: 'business.branches',
                label: 'Branches',
                route: 'settings.branches.index',
                permission: ['branches.view'],
            },
            {
                key: 'business.warehouses',
                label: 'Warehouses',
                route: 'settings.warehouses.index',
                permission: ['warehouses.view'],
            },
        ],
    },
    {
        key: 'nav.inventory',
        module: 'inventory',
        icon: CubeIcon,
        activePattern: 'inventory.*',
        children: [
            {
                key: 'inventory.overview',
                label: 'Overview',
                route: 'inventory.dashboard',
                permission: ['inventory.view'],
            },
            {
                key: 'inventory.products',
                label: 'Products',
                route: 'inventory.products.index',
                permission: ['products.view'],
            },
            {
                key: 'inventory.categories',
                label: 'Categories',
                route: 'inventory.categories.index',
                permission: ['categories.view'],
            },
            {
                key: 'inventory.brands',
                label: 'Brands',
                route: 'inventory.brands.index',
                permission: ['brands.view'],
            },
            {
                key: 'inventory.units',
                label: 'Units',
                route: 'inventory.units.index',
                permission: ['units.view'],
            },
            {
                key: 'inventory.attributes',
                label: 'Attributes',
                route: 'inventory.attributes.index',
                permission: ['attributes.view'],
            },
            {
                key: 'inventory.collections',
                label: 'Collections',
                route: 'inventory.collections.index',
                permission: ['collections.view'],
            },
            {
                key: 'inventory.tags',
                label: 'Tags',
                route: 'inventory.tags.index',
                permission: ['tags.view'],
            },
            {
                key: 'inventory.stock',
                label: 'Stock Adjustments',
                route: 'inventory.stock-adjustments.index',
                permission: ['stock_adjustments.view'],
            },
            {
                key: 'inventory.transfers',
                label: 'Transfers',
                route: 'inventory.stock-transfers.index',
                permission: ['stock_transfers.view'],
            },
            {
                key: 'inventory.counts',
                label: 'Stock Counts',
                route: 'inventory.counts.index',
                permission: ['inventory_counts.view'],
            },
        ],
    },
    {
        key: 'nav.purchasing',
        module: 'purchasing',
        icon: TruckIcon,
        children: [
            {
                key: 'purchasing.overview',
                label: 'Overview',
                route: 'purchasing.dashboard',
                permission: ['purchase_orders.view'],
            },
            {
                key: 'purchasing.suppliers',
                label: 'Suppliers',
                route: 'inventory.suppliers.index',
                module: 'inventory',
                permission: ['suppliers.view'],
            },
            {
                key: 'purchasing.orders',
                label: 'Purchase Orders',
                route: 'purchasing.orders.index',
                permission: ['purchase_orders.view'],
            },
            {
                key: 'purchasing.goodsReceived',
                label: 'Goods Received',
                route: 'purchasing.goods-received.index',
                permission: ['goods_received.view'],
            },
        ],
    },
    {
        key: 'nav.sales',
        module: 'sales',
        icon: ShoppingCartIcon,
        activePattern: 'sales.*',
        children: [
            {
                key: 'sales.overview',
                label: 'Overview',
                route: 'sales.dashboard',
                permission: ['sales.view'],
            },
            {
                key: 'sales.pos',
                label: 'POS',
                route: 'pos.terminal',
                permission: ['pos.view'],
            },
            {
                key: 'sales.orders',
                label: 'Orders',
                route: 'sales.orders.index',
                permission: ['sales.view'],
            },
            {
                key: 'sales.returns',
                label: 'Returns',
                route: 'sales.returns.index',
                permission: ['sales_returns.view'],
            },
        ],
    },
    {
        key: 'nav.finance',
        module: 'finance',
        icon: CalculatorIcon,
        // Ziggy's route().current() only escapes "." and "*", so an
        // unescaped regex alternation passes through untouched — this
        activePattern: '(accounting|finance).*',
        children: [
            {
                key: 'finance.overview',
                label: 'Overview',
                route: 'finance.dashboard',
                permission: ['finance.view'],
            },
            {
                key: 'finance.journal',
                label: 'Journal Entries',
                route: 'finance.journal.index',
                permission: ['finance.view'],
            },
            {
                key: 'finance.accounts',
                label: 'Chart of Accounts',
                route: 'finance.accounts.index',
                permission: ['finance.view'],
            },
            {
                key: 'finance.bank',
                label: 'Bank',
                route: 'finance.bank.index',
                permission: ['finance.view'],
            },
            {
                key: 'finance.expenses',
                label: 'Expenses',
                route: 'accounting.expenses.index',
                permission: ['accounting.view'],
            },
            {
                key: 'finance.income',
                label: 'Income',
                route: 'accounting.income.index',
                permission: ['accounting.view'],
            },
        ],
    },
    {
        key: 'nav.crm',
        module: 'crm',
        icon: UserGroupIcon,
        children: [
            {
                key: 'crm.customers',
                label: 'Customers',
                route: 'sales.customers.index',
                module: 'sales',
                permission: ['customers.view'],
            },
            {
                key: 'crm.overview',
                label: 'CRM Dashboard',
                route: 'crm.dashboard',
                permission: ['crm.view'],
            },
            {
                key: 'crm.groups',
                label: 'Customer Groups',
                route: 'crm.customer-groups.index',
                permission: ['crm.view'],
            },
            {
                key: 'crm.tags',
                label: 'Customer Tags',
                route: 'crm.customer-tags.index',
                permission: ['crm.view'],
            },
            {
                key: 'crm.loyalty',
                label: 'Loyalty Program',
                route: 'crm.loyalty.dashboard',
                permission: ['crm.view'],
            },
            {
                key: 'crm.campaigns',
                label: 'Marketing Campaigns',
                route: 'crm.campaigns.index',
                permission: ['crm.view'],
            },
            {
                key: 'crm.feedback',
                label: 'Feedback',
                route: 'crm.feedback.index',
                permission: ['crm.view'],
            },
        ],
    },
    {
        key: 'nav.website',
        module: 'website',
        icon: GlobeAltIcon,
        activePattern: 'website.*',
        children: [
            {
                key: 'website.dashboard',
                label: 'Dashboard',
                route: 'website.dashboard',
                permission: ['website.view'],
            },
            {
                key: 'website.pages',
                label: 'Pages',
                route: 'website.pages',
                permission: ['website.view'],
            },
            {
                key: 'website.enquiries',
                label: 'Enquiries',
                route: 'website.enquiries.index',
                permission: ['website.view'],
            },
            {
                key: 'website.blog',
                label: 'Blog',
                route: 'website.blog.index',
                permission: ['website.view'],
            },
        ],
    },
    {
        key: 'nav.employees',
        module: 'employees',
        icon: UsersIcon,
        activePattern: 'payroll.*',
        children: [
            {
                key: 'employees.staff',
                label: 'Staff',
                route: 'settings.employees.index',
                permission: ['employees.view'],
            },
            // No permission by design — this is where an employee clocks
            // in and out. The controller scopes the roster instead, so you
            // see your own record and the team's only if allowed.
            {
                key: 'employees.attendance',
                label: 'Attendance',
                route: 'payroll.attendance.index',
            },
            {
                key: 'employees.payroll',
                label: 'Payroll',
                route: 'payroll.dashboard',
                permission: ['payroll.view', 'payroll.manage'],
            },
            // Likewise: applying for your own leave is not privileged.
            {
                key: 'employees.leave',
                label: 'Leave',
                route: 'payroll.leave.index',
            },
        ],
    },
    // The hub filters its own catalog per permission, so anyone may open
    // it — they simply see only the report families they're entitled to.
    {
        key: 'nav.reports',
        module: 'reports',
        route: 'reports.index',
        icon: ChartBarIcon,
        activePattern: 'reports.*',
    },
    {
        key: 'nav.settings',
        module: 'settings',
        icon: Cog6ToothIcon,
        children: [
            {
                key: 'settings.roles',
                label: 'Roles & Permissions',
                route: 'settings.roles.index',
                permission: ['roles.view'],
            },
            {
                key: 'settings.backups',
                label: 'Backup & Restore',
                route: 'settings.backups.index',
                permission: ['backups.view'],
            },
            {
                key: 'settings.subscription',
                label: 'Subscription',
                route: 'settings.subscription.show',
            },
        ],
    },
    /*
     * No `module` and no `permission`, unlike everything above.
     *
     * Both would be actively harmful here. A business whose Sales module
     * was switched off by mistake needs to be able to say so, and a
     * cashier who cannot complete a sale is the person who knows it is
     * broken — gating the way to report a problem behind the thing that
     * is broken is how support requests never arrive.
     */
    {
        key: 'nav.support',
        route: 'support.index',
        icon: LifebuoyIcon,
        activePattern: 'support.*',
    },
];

const ALL_NAV_LEAVES: Array<NavLeaf & { sectionKey: string }> =
    NAV_SECTIONS.flatMap((section) => {
        if (section.children) {
            return section.children
                .filter((leaf) => !leaf.soon && leaf.route)
                .map((leaf) => ({ ...leaf, sectionKey: section.key }));
        }

        if (!section.soon && section.route) {
            return [
                {
                    key: section.key,
                    label: '',
                    route: section.route,
                    activePattern: section.activePattern,
                    sectionKey: section.key,
                },
            ];
        }

        return [];
    });

const QUICK_CREATE_ITEMS = [
    {
        label: 'New Sale',
        route: 'pos.terminal',
        icon: ShoppingCartIcon,
        module: 'sales',
        permission: ['pos.view'],
    },
    {
        label: 'Add Product',
        route: 'inventory.products.create',
        icon: PlusIcon,
        module: 'inventory',
        permission: ['products.create'],
    },
    {
        label: 'Add Customer',
        route: 'sales.customers.index',
        icon: UserPlusIcon,
        module: 'sales',
        permission: ['customers.view'],
    },
    {
        label: 'Add Supplier',
        route: 'inventory.suppliers.index',
        icon: BuildingStorefrontIcon,
        module: 'inventory',
        permission: ['suppliers.view'],
    },
    {
        label: 'Add Expense',
        route: 'accounting.expenses.index',
        icon: CalculatorIcon,
        module: 'finance',
        permission: ['accounting.view'],
    },
    {
        label: 'Add Income',
        route: 'accounting.income.index',
        icon: BanknotesIcon,
        module: 'finance',
        permission: ['accounting.view'],
    },
    {
        label: 'P&L Report',
        route: 'finance.reports.profit-and-loss',
        icon: CreditCardIcon,
        module: 'finance',
        permission: ['finance.view', 'accounting.view'],
    },
];

/**
 * Does the user hold at least one of the listed permissions?
 *
 * No list means "open to everyone" — used for the handful of destinations
 * that genuinely are (the dashboard, your own time clock, your own leave).
 */
function isPermitted(
    granted: string[] | undefined,
    required?: string[],
): boolean {
    if (!required || required.length === 0) return true;

    // `granted` comes from shared props and is always an array in practice.
    // Guarded anyway: this runs during the layout's render, so being wrong
    // about it costs the whole page rather than one menu item.
    //
    // Iterating `required` (one or two entries) rather than `granted` (171
    // for an owner) keeps this cheap — it runs for every nav leaf.
    const held = granted ?? [];

    return required.some((permission) => held.includes(permission));
}

/**
 * Is this section switched on for the business?
 *
 * Takes the list of sections that are switched OFF, not the ones that are
 * on. That way an entry whose module the server never mentioned stays
 * visible — an unseeded or half-configured installation behaves exactly as
 * it did before module gating existed, rather than showing an empty menu.
 */
function isInstalled(hidden: string[], module?: string): boolean {
    if (!module) return true;

    return !hidden.includes(module);
}

/**
 * The sidebar as this particular user should see it.
 *
 * Two independent gates, and they mean different things. **Module** is
 * "does this business have this section at all", set by the Super Admin.
 * **Permission** is "may this employee use it", set by the business owner.
 * A section fails if either says no, and a leaf can name its own module
 * when its route lives under a different prefix than the section it is
 * displayed in — Customers sits under CRM but is served by Sales.
 *
 * Filtering happens here rather than on the server because both lists are
 * already shared props — and doing it in one pass means a group whose
 * every child is hidden disappears too, instead of leaving an empty
 * expandable heading behind.
 */
function visibleSections(granted: string[], hidden: string[]): NavSection[] {
    return NAV_SECTIONS.reduce<NavSection[]>((sections, section) => {
        if (!isInstalled(hidden, section.module)) return sections;

        if (!section.children) {
            if (isPermitted(granted, section.permission))
                sections.push(section);

            return sections;
        }

        const children = section.children.filter(
            (leaf) =>
                isPermitted(granted, leaf.permission) &&
                isInstalled(hidden, leaf.module ?? section.module),
        );

        if (children.length > 0) sections.push({ ...section, children });

        return sections;
    }, []);
}

function SoonRow({
    label,
    icon: Icon,
    indent,
}: {
    label: string;
    icon?: typeof Squares2X2Icon;
    indent?: boolean;
}) {
    return (
        <div
            className={`flex cursor-not-allowed items-center justify-between rounded-md py-2 text-sm text-gray-400 dark:text-gray-600 ${
                indent ? 'py-1.5 pe-3 ps-3' : 'px-3 py-2'
            }`}
        >
            <span className="flex items-center gap-3">
                {Icon && <Icon className="h-5 w-5" />}
                {label}
            </span>
            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                Soon
            </span>
        </div>
    );
}

function LeafLink({
    label,
    route: routeName,
    activePattern,
    isFavorite,
    onToggleFavorite,
}: {
    label: string;
    route: string;
    activePattern?: string;
    isFavorite: boolean;
    onToggleFavorite: () => void;
}) {
    const href = safeRoute(routeName);
    const active = route().current(activePattern ?? routeName);

    // A destination we can't resolve is dropped rather than rendered — see
    // safeRoute. Returning null here is what stops one stale entry from
    // taking the whole layout, and with it every page, down.
    if (href === null) return null;

    return (
        <div
            className={`group relative flex items-center justify-between rounded-md text-sm transition duration-150 ease-in-out ${
                active
                    ? 'bg-indigo-50 font-semibold text-indigo-700 before:absolute before:inset-y-1 before:-start-2 before:w-0.5 before:rounded-full before:bg-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300 dark:before:bg-indigo-400'
                    : 'font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100'
            }`}
        >
            <Link
                href={href}
                className="flex flex-1 items-center py-1.5 pe-3 ps-3"
            >
                {label}
            </Link>
            <button
                type="button"
                onClick={onToggleFavorite}
                aria-label={
                    isFavorite ? 'Remove from favorites' : 'Add to favorites'
                }
                className={`me-2 shrink-0 rounded p-1 opacity-0 group-hover:opacity-100 ${isFavorite ? 'opacity-100' : ''}`}
            >
                {isFavorite ? (
                    <StarSolidIcon className="h-4 w-4 text-amber-500" />
                ) : (
                    <StarOutlineIcon className="h-4 w-4 text-gray-400" />
                )}
            </button>
        </div>
    );
}

function SectionLink({
    section,
    label,
    isFavorite,
    onToggleFavorite,
}: {
    section: NavSection;
    label: string;
    isFavorite: boolean;
    onToggleFavorite: () => void;
}) {
    const Icon = section.icon;
    const href = safeRoute(section.route!);
    const active = route().current(section.activePattern ?? section.route!);

    if (href === null) return null;

    return (
        <div
            className={`group relative flex items-center justify-between rounded-md text-sm transition duration-150 ease-in-out ${
                active
                    ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                    : 'font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100'
            }`}
        >
            <Link
                href={href}
                className="flex flex-1 items-center gap-3 px-3 py-2"
            >
                <Icon className="h-5 w-5" />
                {label}
            </Link>
            <button
                type="button"
                onClick={onToggleFavorite}
                aria-label={
                    isFavorite ? 'Remove from favorites' : 'Add to favorites'
                }
                className={`me-2 shrink-0 rounded p-1 opacity-0 group-hover:opacity-100 ${isFavorite ? 'opacity-100' : ''}`}
            >
                {isFavorite ? (
                    <StarSolidIcon className="h-4 w-4 text-amber-500" />
                ) : (
                    <StarOutlineIcon className="h-4 w-4 text-gray-400" />
                )}
            </button>
        </div>
    );
}

function SidebarContent({
    t,
    subscription,
    businessName,
    permissions,
    hiddenModules,
}: {
    t: (key: string) => string;
    subscription: Subscription | null;
    businessName: string | null;
    permissions: string[];
    hiddenModules: string[];
}) {
    const { favorites, toggle: toggleFavorite } = useSidebarFavorites();
    const { collapsed, toggle: toggleCollapsed } = useCollapsedNavGroups();
    const recentlyVisited = useRecentlyVisited();

    const sections = useMemo(
        () => visibleSections(permissions, hiddenModules),
        [permissions, hiddenModules],
    );

    // Favourites are stored by route name and survive a change of role, so
    // they have to be re-checked against permissions rather than trusted —
    // otherwise a demoted user keeps a pinned shortcut to a screen that now
    // 403s.
    const favoriteLeaves = useMemo(
        () =>
            ALL_NAV_LEAVES.filter(
                (leaf) =>
                    favorites.includes(leaf.route!) &&
                    isPermitted(permissions, leaf.permission) &&
                    isInstalled(hiddenModules, leaf.module),
            ),
        [favorites, permissions, hiddenModules],
    );

    return (
        <>
            <Link href="/" className="flex items-center gap-2 px-5 py-4">
                <ApplicationLogo className="block h-8 w-auto fill-current text-indigo-600" />
                <span className="text-lg font-bold text-gray-900 dark:text-gray-100">
                    BiasharaMax
                </span>
            </Link>

            {businessName && (
                <Link
                    href={route('settings.business.edit')}
                    className="mx-3 mb-4 flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-indigo-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:border-indigo-500/40 dark:hover:bg-gray-800/60"
                >
                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-indigo-600 text-xs font-semibold text-white">
                        {businessName.charAt(0).toUpperCase()}
                    </span>
                    <span className="truncate text-sm font-medium text-gray-700 dark:text-gray-200">
                        {businessName}
                    </span>
                </Link>
            )}

            <nav className="flex-1 space-y-4 overflow-y-auto px-3 pb-6">
                {favoriteLeaves.length > 0 && (
                    <div className="space-y-1 pb-3">
                        <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {t('nav.favorites')}
                        </p>
                        {favoriteLeaves.map((leaf) => (
                            <LeafLink
                                key={`fav-${leaf.sectionKey}-${leaf.key}`}
                                label={leaf.label || t(leaf.sectionKey)}
                                route={leaf.route!}
                                activePattern={leaf.activePattern}
                                isFavorite
                                onToggleFavorite={() =>
                                    toggleFavorite(leaf.route!)
                                }
                            />
                        ))}
                    </div>
                )}

                {sections.map((section) => {
                    const label = t(section.key);

                    if (section.soon) {
                        return (
                            <SoonRow
                                key={section.key}
                                label={label}
                                icon={section.icon}
                            />
                        );
                    }

                    if (!section.children) {
                        return (
                            <SectionLink
                                key={section.key}
                                section={section}
                                label={label}
                                isFavorite={favorites.includes(section.route!)}
                                onToggleFavorite={() =>
                                    toggleFavorite(section.route!)
                                }
                            />
                        );
                    }

                    const isCollapsed = collapsed[section.key];
                    const Icon = section.icon;

                    return (
                        <div key={section.key}>
                            <button
                                type="button"
                                onClick={() => toggleCollapsed(section.key)}
                                className="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500 transition duration-150 ease-in-out hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                            >
                                <span className="flex items-center gap-2.5">
                                    <Icon className="h-4 w-4" />
                                    {label}
                                </span>
                                {isCollapsed ? (
                                    <ChevronRightIcon className="h-3.5 w-3.5 text-gray-400" />
                                ) : (
                                    <ChevronDownIcon className="h-3.5 w-3.5 text-gray-400" />
                                )}
                            </button>

                            {!isCollapsed && (
                                <div className="ms-[1.15rem] space-y-0.5 border-s border-gray-200 ps-2 dark:border-gray-800">
                                    {section.children.map((leaf) =>
                                        leaf.soon || !leaf.route ? (
                                            <SoonRow
                                                key={leaf.key}
                                                label={leaf.label}
                                                indent
                                            />
                                        ) : (
                                            <LeafLink
                                                key={leaf.key}
                                                label={leaf.label}
                                                route={leaf.route}
                                                activePattern={
                                                    leaf.activePattern
                                                }
                                                isFavorite={favorites.includes(
                                                    leaf.route,
                                                )}
                                                onToggleFavorite={() =>
                                                    toggleFavorite(leaf.route!)
                                                }
                                            />
                                        ),
                                    )}
                                </div>
                            )}
                        </div>
                    );
                })}

                {recentlyVisited.length > 0 && (
                    <div className="space-y-1 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <p className="flex items-center gap-1.5 px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <ClockIcon className="h-3.5 w-3.5" />{' '}
                            {t('nav.recentlyVisited')}
                        </p>
                        {recentlyVisited.map((visited) => (
                            <Link
                                key={visited.url}
                                href={visited.url}
                                className="block truncate rounded-md px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                            >
                                {visited.title}
                            </Link>
                        ))}
                    </div>
                )}
            </nav>

            {subscription && (
                <SubscriptionCard
                    subscription={subscription}
                    href={route('settings.subscription.show')}
                />
            )}
        </>
    );
}

function AuthenticatedInner({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, impersonating } = usePage().props;
    const user = auth.user;

    // Surfaces server flash messages as toasts. Mounted here, once, so
    // every `->with('status', ...)` in the app gets feedback without any
    // page having to opt in.
    useFlashToasts();
    const confirmSignOut = useSignOutConfirm();

    const quickCreateItems = useMemo(
        () =>
            QUICK_CREATE_ITEMS.filter(
                (item) =>
                    isPermitted(auth.permissions, item.permission) &&
                    isInstalled(auth.hiddenModules, item.module),
            )
                // Resolved here, so an unresolvable shortcut disappears from
                // the menu instead of throwing while the topbar renders.
                // flatMap narrows `string | null` to `string` without a cast.
                .flatMap((item) => {
                    const href = safeRoute(item.route);

                    return href === null ? [] : [{ ...item, href }];
                }),
        [auth.permissions, auth.hiddenModules],
    );

    const { isDark, toggle: toggleDarkMode } = useDarkMode();
    const { t } = useLocale();
    const { currency, currencies, setCurrencyCode } = useCurrency();
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);

    return (
        <div className="flex h-screen flex-col overflow-hidden bg-gray-50 dark:bg-gray-950">
            {impersonating && (
                <div className="flex shrink-0 items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-sm font-medium text-white">
                    You're viewing this account as {user.name} via SuperAdmin
                    impersonation.
                    <button
                        type="button"
                        onClick={() => router.post(route('impersonation.stop'))}
                        className="rounded-md bg-white/20 px-2 py-0.5 font-semibold hover:bg-white/30"
                    >
                        Stop impersonating
                    </button>
                </div>
            )}

            <div className="flex flex-1 overflow-hidden">
                <aside className="hidden w-64 shrink-0 flex-col border-r border-gray-200 bg-white dark:border-gray-800/80 dark:bg-gray-900 lg:flex">
                    {/*
                      The navigation gets its own boundary. It is chrome:
                      if it fails, the user should still be able to read and
                      use the page they are on, rather than losing the whole
                      application to a broken menu entry.
                    */}
                    <ErrorBoundary fallbackTitle="The menu didn't load">
                        <SidebarContent
                            t={t}
                            subscription={auth.subscription}
                            businessName={auth.business?.name ?? null}
                            permissions={auth.permissions}
                            hiddenModules={auth.hiddenModules}
                        />
                    </ErrorBoundary>
                </aside>

                <Transition show={mobileSidebarOpen} as={Fragment}>
                    <Dialog
                        onClose={() => setMobileSidebarOpen(false)}
                        className="lg:hidden"
                    >
                        <TransitionChild
                            enter="transition ease-out duration-200"
                            enterFrom="opacity-0"
                            enterTo="opacity-100"
                            leave="transition ease-in duration-150"
                            leaveFrom="opacity-100"
                            leaveTo="opacity-0"
                        >
                            <div className="fixed inset-0 z-40 bg-gray-900/50" />
                        </TransitionChild>

                        <TransitionChild
                            enter="transition ease-out duration-200"
                            enterFrom="-translate-x-full"
                            enterTo="translate-x-0"
                            leave="transition ease-in duration-150"
                            leaveFrom="translate-x-0"
                            leaveTo="-translate-x-full"
                        >
                            <DialogPanel className="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-white dark:bg-gray-900">
                                <button
                                    type="button"
                                    onClick={() => setMobileSidebarOpen(false)}
                                    className="absolute right-3 top-5 rounded-md p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                >
                                    <XMarkIcon className="h-6 w-6" />
                                </button>
                                <SidebarContent
                                    t={t}
                                    subscription={auth.subscription}
                                    businessName={auth.business?.name ?? null}
                                    permissions={auth.permissions}
                                    hiddenModules={auth.hiddenModules}
                                />
                            </DialogPanel>
                        </TransitionChild>
                    </Dialog>
                </Transition>

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="flex h-16 shrink-0 items-center gap-4 border-b border-gray-200 bg-white px-4 dark:border-gray-800/80 dark:bg-gray-900 sm:px-6">
                        <button
                            type="button"
                            onClick={() => setMobileSidebarOpen(true)}
                            className="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 lg:hidden"
                        >
                            <Bars3Icon className="h-6 w-6" />
                        </button>

                        <div className="w-64">
                            <GlobalSearch />
                        </div>

                        <button
                            type="button"
                            onClick={() =>
                                window.dispatchEvent(
                                    new Event(OPEN_BUSINESS_ASSISTANT_EVENT),
                                )
                            }
                            className="ml-auto hidden items-center gap-1.5 rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 sm:flex"
                        >
                            <SparklesIcon className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                            Ask AI
                        </button>

                        <div className="flex items-center gap-1">
                            {/* Same rule as the sidebar — a shortcut to a
                                screen the user can't open is just a 403 in
                                waiting. Hidden entirely if nothing is left. */}
                            {quickCreateItems.length > 0 && (
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button
                                            type="button"
                                            aria-label="Quick create"
                                            className="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                                        >
                                            <PlusIcon className="h-6 w-6" />
                                        </button>
                                    </Dropdown.Trigger>
                                    <Dropdown.Content width="48">
                                        {quickCreateItems.map((item) => {
                                            const Icon = item.icon;

                                            return (
                                                <Dropdown.Link
                                                    key={item.route}
                                                    href={item.href}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Icon className="h-4 w-4" />{' '}
                                                    {item.label}
                                                </Dropdown.Link>
                                            );
                                        })}
                                    </Dropdown.Content>
                                </Dropdown>
                            )}
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        aria-label="Change currency"
                                        className="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                                    >
                                        <CurrencyDollarIcon className="h-6 w-6" />
                                    </button>
                                </Dropdown.Trigger>
                                <Dropdown.Content width="48">
                                    {currencies.map((c) => (
                                        <button
                                            key={c.code}
                                            type="button"
                                            onClick={() =>
                                                setCurrencyCode(c.code)
                                            }
                                            className="flex w-full items-center justify-between px-4 py-2 text-start text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                        >
                                            <span>
                                                {c.code} — {c.name}
                                            </span>
                                            {currency?.code === c.code && (
                                                <CheckIcon className="h-4 w-4 text-indigo-600" />
                                            )}
                                        </button>
                                    ))}
                                    {currencies.length === 0 && (
                                        <p className="px-4 py-2 text-sm text-gray-400">
                                            No currencies configured.
                                        </p>
                                    )}
                                </Dropdown.Content>
                            </Dropdown>
                            <LanguageSwitcher />
                            <button
                                type="button"
                                onClick={toggleDarkMode}
                                aria-label="Toggle dark mode"
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                            >
                                {isDark ? (
                                    <SunIcon className="h-6 w-6" />
                                ) : (
                                    <MoonIcon className="h-6 w-6" />
                                )}
                            </button>
                            <NotificationBell />

                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="ms-1 flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        {user.avatar_url ? (
                                            <img
                                                src={user.avatar_url}
                                                alt={user.name}
                                                className="h-7 w-7 rounded-full object-cover"
                                            />
                                        ) : (
                                            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                                {user.name
                                                    .charAt(0)
                                                    .toUpperCase()}
                                            </span>
                                        )}
                                        <span className="hidden sm:inline">
                                            {user.name}
                                        </span>
                                        <ChevronDownIcon className="h-4 w-4 text-gray-400" />
                                    </button>
                                </Dropdown.Trigger>

                                <Dropdown.Content>
                                    <Dropdown.Link href={route('profile.edit')}>
                                        {t('topbar.profile')}
                                    </Dropdown.Link>
                                    {/*
                                      A button rather than Inertia's
                                      method="post" link: the POST has to
                                      wait for the confirmation, so the
                                      click can't be the thing that submits.
                                    */}
                                    <button
                                        type="button"
                                        onClick={() =>
                                            confirmSignOut({
                                                routeName: 'logout',
                                                name: user?.name,
                                            })
                                        }
                                        className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                                    >
                                        {t('topbar.logout')}
                                    </button>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    </header>

                    {header && (
                        <div className="border-b border-gray-200 bg-white px-4 py-6 dark:border-gray-800/80 dark:bg-gray-900 sm:px-6">
                            {header}
                        </div>
                    )}

                    <main className="flex-1 overflow-y-auto dark:bg-gray-950">
                        {children}
                    </main>
                </div>
            </div>

            <BusinessAssistant />
        </div>
    );
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    return (
        <CurrencyProvider>
            <AuthenticatedInner header={header}>{children}</AuthenticatedInner>
        </CurrencyProvider>
    );
}
