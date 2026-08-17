import ApplicationLogo from '@/Components/ApplicationLogo';
import BiCommandPalette from '@/Components/Bi/BiCommandPalette';
import BiNotificationBell, {
    PlatformNotificationItem,
} from '@/Components/Bi/BiNotificationBell';
import BiSidebar, { BiSidebarGroup } from '@/Components/Bi/BiSidebar';
import BiTopbar from '@/Components/Bi/BiTopbar';
import { useConfirm } from '@/Components/ConfirmDialog';
import Dropdown from '@/Components/Dropdown';
import { CurrencyProvider, useCurrency } from '@/contexts/CurrencyContext';
import { useLocale } from '@/contexts/LocaleContext';
import { useDarkMode } from '@/hooks/useDarkMode';
import { useFlashToasts } from '@/hooks/useFlashToasts';
import { useSignOutConfirm } from '@/hooks/useSignOutConfirm';
import { postJson } from '@/lib/postJson';
import { Locale } from '@/lib/translations';
import { PageProps } from '@/types';
import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import {
    ArrowsRightLeftIcon,
    BanknotesIcon,
    Bars3Icon,
    BellAlertIcon,
    BuildingLibraryIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
    CheckIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    Cog6ToothIcon,
    ComputerDesktopIcon,
    CpuChipIcon,
    CreditCardIcon,
    CurrencyDollarIcon,
    DocumentTextIcon,
    GlobeAltIcon,
    IdentificationIcon,
    KeyIcon,
    LanguageIcon,
    LifebuoyIcon,
    MagnifyingGlassIcon,
    MoonIcon,
    PuzzlePieceIcon,
    RectangleStackIcon,
    ServerStackIcon,
    ShieldCheckIcon,
    ShieldExclamationIcon,
    SparklesIcon,
    Squares2X2Icon,
    SunIcon,
    UserCircleIcon,
    UsersIcon,
    WrenchScrewdriverIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { Link, router, usePage } from '@inertiajs/react';
import { Fragment, PropsWithChildren, useEffect, useState } from 'react';

/**
 * Tailwind classes per status colour.
 *
 * A lookup rather than an interpolated `bg-${colour}-50`, because
 * Tailwind scans source text for complete class names — a constructed
 * one is never emitted, and the badge would render unstyled exactly when
 * something is wrong.
 */
const STATUS_STYLES: Record<
    'success' | 'warning' | 'danger',
    { pill: string; dot: string }
> = {
    success: {
        pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
        dot: 'bg-emerald-500',
    },
    warning: {
        pill: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        dot: 'bg-amber-500',
    },
    danger: {
        pill: 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        dot: 'bg-red-500',
    },
};

const NAV_GROUPS: BiSidebarGroup[] = [
    // ── Overview ──────────────────────────────────────────────────────────
    {
        key: 'top',
        items: [
            {
                key: 'dashboard',
                label: 'Dashboard',
                icon: <Squares2X2Icon className="h-5 w-5" />,
                href: 'platform.dashboard',
            },
        ],
    },

    // ── Tenants ───────────────────────────────────────────────────────────
    {
        key: 'tenants',
        label: 'Tenants',
        items: [
            {
                key: 'businesses',
                label: 'Businesses',
                icon: <BuildingOffice2Icon className="h-5 w-5" />,
                href: 'platform.businesses.index',
            },
            {
                key: 'users',
                label: 'Users',
                icon: <UsersIcon className="h-5 w-5" />,
                href: 'platform.users.index',
            },
            {
                key: 'subscriptions',
                label: 'Subscriptions',
                icon: <CreditCardIcon className="h-5 w-5" />,
                href: 'platform.subscriptions.dashboard',
                activePattern: 'platform.subscriptions.*',
            },
            {
                key: 'licenses',
                label: 'Licenses',
                icon: <KeyIcon className="h-5 w-5" />,
                href: 'platform.licenses.dashboard',
                activePattern: 'platform.licenses.*',
            },
            {
                key: 'registration-codes',
                label: 'Registration Codes',
                icon: <KeyIcon className="h-5 w-5" />,
                href: 'platform.subscriptions.registration-codes.index',
                activePattern: 'platform.subscriptions.registration-codes.*',
            },
        ],
    },

    // ── Finance ───────────────────────────────────────────────────────────
    {
        key: 'finance',
        label: 'Finance',
        items: [
            {
                key: 'finance-dashboard',
                label: 'Finance Dashboard',
                icon: <BanknotesIcon className="h-5 w-5" />,
                href: 'platform.finance.dashboard',
            },
            {
                key: 'payments',
                label: 'Payments',
                icon: <BuildingLibraryIcon className="h-5 w-5" />,
                href: 'platform.finance.payments.index',
                activePattern: 'platform.finance.payments.*',
            },
            {
                key: 'payment-gateways',
                label: 'Payment Gateways',
                icon: <GlobeAltIcon className="h-5 w-5" />,
                href: 'platform.finance.gateways.index',
                activePattern: 'platform.finance.gateways.*',
            },
            {
                key: 'reports',
                label: 'Reports',
                icon: <DocumentTextIcon className="h-5 w-5" />,
                href: 'platform.finance.reports.index',
                activePattern: 'platform.finance.reports.*',
            },
        ],
    },

    // ── Administration ────────────────────────────────────────────────────
    {
        key: 'administration',
        label: 'Administration',
        items: [
            {
                key: 'staff',
                label: 'Platform Admins',
                icon: <IdentificationIcon className="h-5 w-5" />,
                href: 'platform.staff.index',
            },
            {
                key: 'roles',
                label: 'Roles & Permissions',
                icon: <ShieldCheckIcon className="h-5 w-5" />,
                href: 'platform.rbac.platform-roles.index',
                activePattern: 'platform.rbac.*',
            },
            {
                key: 'notifications',
                label: 'Notifications',
                icon: <BellAlertIcon className="h-5 w-5" />,
                href: 'platform.operations.notifications.index',
                activePattern: 'platform.operations.notifications.*',
            },
            {
                key: 'support',
                label: 'Support',
                icon: <LifebuoyIcon className="h-5 w-5" />,
                href: 'platform.operations.support.index',
                activePattern: 'platform.operations.support.*',
            },
        ],
    },

    // ── Configuration ─────────────────────────────────────────────────────
    {
        key: 'configuration',
        label: 'Configuration',
        items: [
            {
                key: 'business-types',
                label: 'Business Types',
                icon: <RectangleStackIcon className="h-5 w-5" />,
                href: 'platform.business-types.index',
                activePattern: 'platform.business-types.*',
            },
            {
                key: 'modules',
                label: 'Modules',
                icon: <PuzzlePieceIcon className="h-5 w-5" />,
                href: 'platform.modules.index',
                activePattern: 'platform.modules.*',
            },
            {
                key: 'website-templates',
                label: 'Website Templates',
                icon: <ComputerDesktopIcon className="h-5 w-5" />,
                href: 'platform.operations.website-templates.index',
                activePattern: 'platform.operations.website-templates.*',
            },
        ],
    },

    // ── Security ──────────────────────────────────────────────────────────
    //
    // Was "Operations". Renamed rather than split: these four screens are
    // the ones an admin opens when answering "is anything wrong, and who
    // did it" — health, threats, the audit trail, and the runtime state
    // behind all three. Grouping them under one heading is what makes that
    // a single place to look rather than four unrelated links.
    {
        key: 'security',
        label: 'Security',
        items: [
            {
                key: 'system-monitoring',
                label: 'System Monitoring',
                icon: <ServerStackIcon className="h-5 w-5" />,
                href: 'platform.operations.monitoring.index',
                activePattern: 'platform.operations.monitoring.*',
            },
            {
                key: 'security-center',
                label: 'Security Center',
                icon: <ShieldExclamationIcon className="h-5 w-5" />,
                href: 'platform.operations.security.index',
                activePattern: 'platform.operations.security.*',
            },
            {
                key: 'audit-logs',
                label: 'Audit Logs',
                icon: <ChartBarIcon className="h-5 w-5" />,
                href: 'platform.audit-logs.index',
            },
            {
                key: 'developer-center',
                label: 'Developer Center',
                icon: <CpuChipIcon className="h-5 w-5" />,
                href: 'platform.operations.developer.index',
                activePattern: 'platform.operations.developer.*',
            },
        ],
    },

    // ── System ────────────────────────────────────────────────────────────
    {
        key: 'system',
        label: 'System',
        items: [
            {
                key: 'settings',
                label: 'Settings',
                icon: <Cog6ToothIcon className="h-5 w-5" />,
                href: 'platform.system.settings.index',
                activePattern: 'platform.system.settings.*',
            },
            {
                key: 'integrations',
                label: 'Integrations',
                icon: <ArrowsRightLeftIcon className="h-5 w-5" />,
                href: 'platform.system.integrations.index',
                activePattern: 'platform.system.integrations.*',
            },
            {
                key: 'ai-insights',
                label: 'AI Insights',
                icon: <SparklesIcon className="h-5 w-5" />,
                href: 'platform.system.ai-insights.index',
                activePattern: 'platform.system.ai-insights.*',
            },
            {
                key: 'backup',
                label: 'Backup & Restore',
                icon: <WrenchScrewdriverIcon className="h-5 w-5" />,
                href: 'platform.system.backups.index',
                activePattern: 'platform.system.backups.*',
            },
        ],
    },
];

const STORAGE_KEY = 'biasharaos-admin-sidebar-collapsed';

const LANGUAGES: { code: Locale; label: string }[] = [
    { code: 'en', label: 'English' },
    { code: 'sw', label: 'Kiswahili' },
];

function NotificationBadge({ count }: { count: number }) {
    if (count === 0) return null;

    return (
        <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-100 px-1 text-[10px] font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">
            {count > 9 ? '9+' : count}
        </span>
    );
}

function PlatformLayoutInner({ children }: PropsWithChildren) {
    const { platformAuth, availableCurrencies } = usePage<PageProps>().props;

    // Same wiring as the tenant layout — the platform side flashes ~137
    // status messages of its own that were being discarded too.
    useFlashToasts();
    const confirmSignOut = useSignOutConfirm();
    const confirm = useConfirm();

    /**
     * Switches to the Filament panel and remembers the choice.
     *
     * A POST rather than a link, because it writes a preference before
     * navigating. No `window.location` here on purpose: the controller
     * answers with `Inertia::location()`, which returns 409 and an
     * `X-Inertia-Location` header, and the router performs the full page
     * visit itself.
     *
     * That detail is the whole fix for a bug this had on first pass.
     * Returning an ordinary redirect made Inertia follow it, ask
     * /platform for an Inertia payload, get a Livewire HTML page back and
     * render it inside its error overlay — the panel appeared in a modal
     * on top of /admin, and only a manual URL edit escaped it.
     *
     * The confirmation names what is only available here rather than
     * asking a generic "are you sure". Nothing about this is
     * destructive; the risk is that an admin does not know the two
     * surfaces differ, and a warning that does not say how prevents
     * nothing.
     */
    const missingFromFilament = platformAuth.surface?.missingFromFilament ?? [];
    const platformStatus = platformAuth.status;

    const switchToFilament = () => {
        confirm({
            title: 'Switch to the Filament panel?',
            message:
                missingFromFilament.length > 0
                    ? `These screens are only available here: ${missingFromFilament.join(', ')}. You can switch back from the panel’s top bar at any time.`
                    : 'You can switch back from the panel’s top bar at any time.',
            confirmLabel: 'Switch',
            cancelLabel: 'Stay here',
            tone: 'info',
            icon: ArrowsRightLeftIcon,
            onConfirm: () =>
                router.post(route('platform.preferences.admin-surface'), {
                    surface: 'platform',
                }),
        });
    };
    const { isDark, toggle: toggleDarkMode } = useDarkMode();
    const { locale, setLocale, t } = useLocale();
    const { currency: selectedCurrency, setCurrencyCode: selectCurrency } =
        useCurrency();
    const [mobileOpen, setMobileOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [hoverPeek, setHoverPeek] = useState(false);
    const [notifications, setNotifications] = useState<
        PlatformNotificationItem[]
    >([]);
    const [notificationsLoaded, setNotificationsLoaded] = useState(false);
    const [collapsed, setCollapsed] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.localStorage.getItem(STORAGE_KEY) === '1',
    );

    useEffect(() => {
        fetch(route('platform.notifications.live'))
            .then((res) => res.json())
            .then((data) => setNotifications(data.items ?? []))
            .catch(() => {})
            .finally(() => setNotificationsLoaded(true));
    }, []);

    /**
     * Dismissing removes the item locally first, then tells the server.
     *
     * The list is optimistic on purpose — a dismissal is cheap, reversible
     * by the underlying problem recurring, and waiting on a round trip to
     * make a badge disappear feels broken. On failure the item is put back
     * rather than silently staying gone, because a notification that
     * looks dismissed but is not is worse than one that refuses to go.
     *
     * `postJson`, not `router.post`: these endpoints answer with JSON,
     * and Inertia's router rejects any response that is not an Inertia
     * payload — rendering the raw JSON in a full-screen error overlay
     * that looks like a crash.
     */
    const dismissNotification = (item: PlatformNotificationItem) => {
        const previous = notifications;
        setNotifications((current) => current.filter((n) => n.id !== item.id));

        postJson(route('platform.notifications.dismiss'), {
            key: item.id,
        }).catch(() => setNotifications(previous));
    };

    const dismissAllNotifications = () => {
        const previous = notifications;
        setNotifications([]);

        postJson(route('platform.notifications.dismiss-all')).catch(() =>
            setNotifications(previous),
        );
    };

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setSearchOpen(true);
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    const toggleCollapsed = () => {
        setCollapsed((previous) => {
            const next = !previous;
            window.localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
            return next;
        });
    };

    const businessAlerts = notifications.filter(
        (n) => n.type === 'business',
    ).length;
    const securityAlerts = notifications.filter(
        (n) => n.type === 'security',
    ).length;

    // Falls back to the English label when a key is missing, which is how
    // the item labels below already behave — a group whose translation
    // hasn't been written yet shows English rather than the raw key.
    const translate = (key: string, fallback?: string) =>
        t(key) === key ? fallback : t(key);

    /*
     * Exactly one sidebar entry highlights, even when two patterns match.
     *
     * Nav patterns nest: Registration Codes is `platform.subscriptions.
     * registration-codes.*`, which also satisfies Subscriptions'
     * `platform.subscriptions.*`. Testing each entry independently lit
     * both, so the sidebar claimed you were in two places at once.
     *
     * Narrowing the parent pattern would fix this one case and leave the
     * next nested route to rediscover it. Preferring the most specific
     * match is a rule rather than a patch: whichever matching pattern
     * names the most path segments wins, which is the same "longest
     * prefix" logic routers themselves use.
     */
    const specificity = (pattern: string): number =>
        pattern.replace(/\.?\*$/, '').split('.').length;

    const mostSpecificMatch = NAV_GROUPS.flatMap((group) => group.items)
        .filter((item) => item.href && route().current(activePattern(item)))
        .map(activePattern)
        .reduce(
            (best, pattern) =>
                specificity(pattern) > specificity(best) ? pattern : best,
            '',
        );

    const groups: BiSidebarGroup[] = NAV_GROUPS.map((group) => ({
        ...group,
        // Group headings were rendering hardcoded English regardless of
        // locale: `platform.section.*` keys existed in translations.ts but
        // nothing ever read them, so a Kiswahili admin saw translated links
        // sitting under untranslated headings.
        label: group.label
            ? translate(`platform.section.${toCamel(group.key)}`, group.label)
            : undefined,
        items: group.items.map((item) => ({
            ...item,
            label:
                t(`platform.nav.${toCamel(item.key)}`) ===
                `platform.nav.${toCamel(item.key)}`
                    ? item.label
                    : t(`platform.nav.${toCamel(item.key)}`),
            href: item.href ? route(item.href) : undefined,
            active: item.href
                ? activePattern(item) === mostSpecificMatch
                : false,
            badge:
                item.key === 'businesses' && businessAlerts > 0 ? (
                    <NotificationBadge count={businessAlerts} />
                ) : item.key === 'security-center' && securityAlerts > 0 ? (
                    <NotificationBadge count={securityAlerts} />
                ) : undefined,
        })),
    }));

    const today = new Date().toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

    const sidebarHeader = (
        <Link
            href={route('platform.dashboard')}
            className="flex items-center gap-2 px-6 py-5"
        >
            <ApplicationLogo className="h-8 w-auto shrink-0 fill-current text-indigo-600" />
            {!collapsed && (
                <span className="flex items-center gap-2 text-lg font-bold text-gray-900 dark:text-gray-100">
                    BiasharaMax
                    <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                        Admin
                    </span>
                </span>
            )}
        </Link>
    );

    return (
        <div className="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-900">
            <div
                className="relative hidden shrink-0 lg:block"
                style={{ width: collapsed ? '5rem' : '18rem' }}
                onMouseEnter={() => collapsed && setHoverPeek(true)}
                onMouseLeave={() => setHoverPeek(false)}
            >
                <aside
                    className={`absolute inset-y-0 left-0 flex flex-col border-r border-gray-200 bg-white shadow-sm transition-all duration-200 dark:border-gray-700 dark:bg-gray-800 ${
                        hoverPeek
                            ? 'w-72 shadow-xl'
                            : collapsed
                              ? 'w-20'
                              : 'w-72'
                    }`}
                    style={{ zIndex: hoverPeek ? 40 : 'auto' }}
                >
                    <BiSidebar
                        groups={groups}
                        collapsed={collapsed && !hoverPeek}
                        header={sidebarHeader}
                    />

                    <button
                        type="button"
                        onClick={toggleCollapsed}
                        className="flex items-center justify-center gap-2 border-t border-gray-100 px-4 py-3 text-sm text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700"
                    >
                        <ChevronLeftIcon
                            className={`h-4 w-4 transition-transform ${collapsed && !hoverPeek ? 'rotate-180' : ''}`}
                        />
                        {(!collapsed || hoverPeek) && 'Collapse'}
                    </button>
                </aside>
            </div>

            <Transition show={mobileOpen} as={Fragment}>
                <Dialog
                    onClose={() => setMobileOpen(false)}
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
                        <DialogPanel className="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-white dark:bg-gray-800">
                            <button
                                type="button"
                                onClick={() => setMobileOpen(false)}
                                className="absolute right-3 top-5 rounded-md p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            >
                                <XMarkIcon className="h-6 w-6" />
                            </button>
                            <BiSidebar groups={groups} header={sidebarHeader} />
                        </DialogPanel>
                    </TransitionChild>
                </Dialog>
            </Transition>

            <BiCommandPalette
                show={searchOpen}
                onClose={() => setSearchOpen(false)}
            />

            <div className="flex min-w-0 flex-1 flex-col">
                <BiTopbar
                    left={
                        <>
                            <button
                                type="button"
                                onClick={() => setMobileOpen(true)}
                                className="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 lg:hidden"
                            >
                                <Bars3Icon className="h-6 w-6" />
                            </button>

                            <button
                                type="button"
                                onClick={() => setSearchOpen(true)}
                                className="flex w-full max-w-sm items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-400 transition hover:border-gray-300 hover:bg-white dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                            >
                                <MagnifyingGlassIcon className="h-4 w-4" />
                                <span className="hidden sm:inline">
                                    {t('topbar.search')}…
                                </span>
                                <kbd className="ml-auto hidden rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] text-gray-400 dark:border-gray-600 dark:bg-gray-800 sm:inline">
                                    ⌘K
                                </kbd>
                            </button>

                            <span className="hidden whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 lg:inline">
                                {today}
                            </span>
                        </>
                    }
                    right={
                        <>
                            {/*
                              Real status, from PlatformStatusBadgeService
                              — the same service and cache entry the
                              Filament top bar reads.

                              This used to be the literal text
                              "Operational" beside a hardcoded green dot,
                              which meant /admin reported the platform
                              healthy while /platform showed it Down. An
                              indicator that cannot report a fault is
                              worse than none, because it reassures.
                            */}
                            {platformStatus && (
                                <span
                                    title={platformStatus.title}
                                    className={`hidden items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium lg:flex ${STATUS_STYLES[platformStatus.color].pill}`}
                                >
                                    <span
                                        className={`h-1.5 w-1.5 rounded-full ${STATUS_STYLES[platformStatus.color].dot}`}
                                    />
                                    {platformStatus.label}
                                </span>
                            )}

                            <button
                                type="button"
                                onClick={switchToFilament}
                                title="Switch to the Filament panel"
                                aria-label="Switch to the Filament panel"
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                            >
                                <ArrowsRightLeftIcon className="h-6 w-6" />
                            </button>

                            <BiNotificationBell
                                items={notifications}
                                loaded={notificationsLoaded}
                                onDismiss={dismissNotification}
                                onDismissAll={dismissAllNotifications}
                            />

                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                                        aria-label="Settings"
                                    >
                                        <Cog6ToothIcon className="h-6 w-6" />
                                    </button>
                                </Dropdown.Trigger>
                                <Dropdown.Content width="48">
                                    <Dropdown.Link
                                        href={route(
                                            'platform.system.settings.index',
                                        )}
                                    >
                                        Platform Settings
                                    </Dropdown.Link>
                                    <Dropdown.Link
                                        href={route(
                                            'platform.system.integrations.index',
                                        )}
                                    >
                                        Integrations
                                    </Dropdown.Link>
                                    <Dropdown.Link
                                        href={route(
                                            'platform.system.backups.index',
                                        )}
                                    >
                                        Backup &amp; Restore
                                    </Dropdown.Link>
                                    <Dropdown.Link
                                        href={route(
                                            'platform.rbac.platform-roles.index',
                                        )}
                                    >
                                        Roles &amp; Permissions
                                    </Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>

                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                                        aria-label="Change currency"
                                    >
                                        <CurrencyDollarIcon className="h-6 w-6" />
                                    </button>
                                </Dropdown.Trigger>
                                <Dropdown.Content width="48">
                                    {availableCurrencies.map((currency) => (
                                        <button
                                            key={currency.code}
                                            type="button"
                                            onClick={() =>
                                                selectCurrency(currency.code)
                                            }
                                            className="flex w-full items-center justify-between px-4 py-2 text-start text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                        >
                                            <span>
                                                {currency.code} —{' '}
                                                {currency.name}
                                            </span>
                                            {selectedCurrency?.code ===
                                                currency.code && (
                                                <CheckIcon className="h-4 w-4 text-indigo-600" />
                                            )}
                                        </button>
                                    ))}
                                    {availableCurrencies.length === 0 && (
                                        <p className="px-4 py-2 text-sm text-gray-400">
                                            No currencies configured.
                                        </p>
                                    )}
                                </Dropdown.Content>
                            </Dropdown>

                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                                        aria-label="Change language"
                                    >
                                        <LanguageIcon className="h-6 w-6" />
                                    </button>
                                </Dropdown.Trigger>
                                <Dropdown.Content width="48">
                                    {LANGUAGES.map((lang) => (
                                        <button
                                            key={lang.code}
                                            type="button"
                                            onClick={() => setLocale(lang.code)}
                                            className="flex w-full items-center justify-between px-4 py-2 text-start text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                        >
                                            {lang.label}
                                            {locale === lang.code && (
                                                <CheckIcon className="h-4 w-4 text-indigo-600" />
                                            )}
                                        </button>
                                    ))}
                                </Dropdown.Content>
                            </Dropdown>

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

                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="ms-1 flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        {platformAuth.user?.avatar_url ? (
                                            <img
                                                src={
                                                    platformAuth.user.avatar_url
                                                }
                                                alt={platformAuth.user.name}
                                                className="h-8 w-8 rounded-full object-cover"
                                            />
                                        ) : (
                                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-semibold text-white">
                                                {platformAuth.user?.name
                                                    .charAt(0)
                                                    .toUpperCase()}
                                            </span>
                                        )}
                                        <span className="hidden sm:inline">
                                            {platformAuth.user?.name}
                                        </span>
                                        <ChevronDownIcon className="h-4 w-4 text-gray-400" />
                                    </button>
                                </Dropdown.Trigger>

                                <Dropdown.Content width="80">
                                    <div className="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                                        <p className="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {platformAuth.user?.name}
                                        </p>
                                        <p className="truncate text-xs text-gray-500 dark:text-gray-400">
                                            {platformAuth.user?.email}
                                        </p>
                                        <span className="mt-1.5 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                            SuperAdmin
                                        </span>
                                    </div>

                                    <div className="py-1">
                                        <Dropdown.Link
                                            href={route(
                                                'platform.profile.edit',
                                            )}
                                            className="flex items-center gap-2.5"
                                        >
                                            <UserCircleIcon className="h-4 w-4 text-gray-400" />
                                            {t('topbar.profile')}
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route(
                                                'platform.system.settings.index',
                                            )}
                                            className="flex items-center gap-2.5"
                                        >
                                            <Cog6ToothIcon className="h-4 w-4 text-gray-400" />
                                            {t('topbar.settings')}
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route(
                                                'platform.audit-logs.index',
                                            )}
                                            className="flex items-center gap-2.5"
                                        >
                                            <ChartBarIcon className="h-4 w-4 text-gray-400" />
                                            {t('topbar.activity')}
                                        </Dropdown.Link>
                                    </div>

                                    <div className="border-t border-gray-100 py-1 dark:border-gray-700">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                confirmSignOut({
                                                    routeName:
                                                        'platform.logout',
                                                    name: platformAuth.user
                                                        ?.name,
                                                })
                                            }
                                            className="block w-full px-4 py-2 text-start text-sm leading-5 text-red-600 transition duration-150 ease-in-out hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                        >
                                            {t('topbar.logout')}
                                        </button>
                                    </div>
                                </Dropdown.Content>
                            </Dropdown>
                        </>
                    }
                />

                <main className="flex-1 overflow-y-auto p-4 sm:p-6">
                    {children}
                </main>
            </div>
        </div>
    );
}

export default function PlatformLayout({ children }: PropsWithChildren) {
    return (
        <CurrencyProvider>
            <PlatformLayoutInner>{children}</PlatformLayoutInner>
        </CurrencyProvider>
    );
}

/**
 * The route pattern that decides whether a nav entry is the current page.
 *
 * Falls back to the entry's own route name plus a wildcard, which is what
 * the active check used inline before. Extracted so the pattern is
 * derived in exactly one place — it is now read twice, and two copies of
 * this fallback drifting apart would silently unhighlight entries.
 */
function activePattern(item: {
    href?: string;
    activePattern?: string;
}): string {
    return item.activePattern ?? `${item.href}*`;
}

function toCamel(key: string): string {
    return key.replace(/-([a-z])/g, (_, letter: string) =>
        letter.toUpperCase(),
    );
}
