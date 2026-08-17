import Modal from '@/Components/Modal';
import { MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

type SearchResult = {
    id: string;
    type: string;
    title: string;
    subtitle: string | null;
    url: string;
};

type SearchGroup = {
    group: string;
    items: SearchResult[];
};

/**
 * Screens reachable from the palette.
 *
 * Resolved on the client rather than fetched: a page has nothing to look
 * up, and making the user wait on a round trip to be told "Products" is
 * spelled the way they just typed it would be the slowest possible way to
 * answer. Each entry names the permission that guards it, so the palette
 * never offers a screen that would 403.
 */
const PAGES: Array<{
    label: string;
    route: string;
    permission?: string;
    /** Dashboard section. Omitted means always present. */
    module?: string;
}> = [
    { label: 'Dashboard', route: 'dashboard' },
    {
        label: 'Products',
        route: 'inventory.products.index',
        module: 'inventory',
        permission: 'products.view',
    },
    {
        label: 'Categories',
        route: 'inventory.categories.index',
        module: 'inventory',
        permission: 'categories.view',
    },
    {
        label: 'Brands',
        route: 'inventory.brands.index',
        module: 'inventory',
        permission: 'brands.view',
    },
    {
        label: 'Units',
        route: 'inventory.units.index',
        module: 'inventory',
        permission: 'units.view',
    },
    {
        label: 'Stock Adjustments',
        route: 'inventory.stock-adjustments.index',
        module: 'inventory',
        permission: 'stock_adjustments.view',
    },
    {
        label: 'Stock Transfers',
        route: 'inventory.stock-transfers.index',
        module: 'inventory',
        permission: 'stock_transfers.view',
    },
    {
        label: 'Stock Counts',
        route: 'inventory.counts.index',
        module: 'inventory',
        permission: 'inventory_counts.view',
    },
    {
        label: 'Suppliers',
        route: 'inventory.suppliers.index',
        module: 'inventory',
        permission: 'suppliers.view',
    },
    {
        label: 'Purchase Orders',
        route: 'purchasing.orders.index',
        module: 'purchasing',
        permission: 'purchase_orders.view',
    },
    {
        label: 'Goods Received',
        route: 'purchasing.goods-received.index',
        module: 'purchasing',
        permission: 'goods_received.view',
    },
    {
        label: 'Point of Sale',
        route: 'pos.terminal',
        module: 'sales',
        permission: 'pos.view',
    },
    {
        label: 'Sales Orders',
        route: 'sales.orders.index',
        module: 'sales',
        permission: 'sales.view',
    },
    {
        label: 'Sales Returns',
        route: 'sales.returns.index',
        module: 'sales',
        permission: 'sales_returns.view',
    },
    {
        label: 'Customers',
        route: 'sales.customers.index',
        module: 'sales',
        permission: 'customers.view',
    },
    {
        label: 'CRM Dashboard',
        route: 'crm.dashboard',
        module: 'crm',
        permission: 'crm.view',
    },
    {
        label: 'Loyalty Program',
        route: 'crm.loyalty.dashboard',
        module: 'crm',
        permission: 'crm.view',
    },
    {
        label: 'Marketing Campaigns',
        route: 'crm.campaigns.index',
        module: 'crm',
        permission: 'crm.view',
    },
    {
        label: 'Expenses',
        route: 'accounting.expenses.index',
        module: 'finance',
        permission: 'accounting.view',
    },
    {
        label: 'Income',
        route: 'accounting.income.index',
        module: 'finance',
        permission: 'accounting.view',
    },
    {
        label: 'Journal Entries',
        route: 'finance.journal.index',
        module: 'finance',
        permission: 'finance.view',
    },
    {
        label: 'Chart of Accounts',
        route: 'finance.accounts.index',
        module: 'finance',
        permission: 'finance.view',
    },
    {
        label: 'Bank Accounts',
        route: 'finance.bank.index',
        module: 'finance',
        permission: 'finance.view',
    },
    { label: 'Reports', route: 'reports.index', module: 'reports' },
    {
        label: 'Attendance',
        route: 'payroll.attendance.index',
        module: 'employees',
    },
    { label: 'Leave', route: 'payroll.leave.index', module: 'employees' },
    {
        label: 'Payroll',
        route: 'payroll.dashboard',
        module: 'employees',
        permission: 'payroll.view',
    },
    {
        label: 'Staff',
        route: 'settings.employees.index',
        module: 'employees',
        permission: 'employees.view',
    },
    {
        label: 'Branches',
        route: 'settings.branches.index',
        module: 'business',
        permission: 'branches.view',
    },
    {
        label: 'Warehouses',
        route: 'settings.warehouses.index',
        module: 'business',
        permission: 'warehouses.view',
    },
    {
        label: 'Roles & Permissions',
        route: 'settings.roles.index',
        module: 'settings',
        permission: 'roles.view',
    },
    {
        label: 'Backup & Restore',
        route: 'settings.backups.index',
        module: 'settings',
        permission: 'backups.view',
    },
    {
        label: 'Business Profile',
        route: 'settings.business.edit',
        module: 'business',
    },
    {
        label: 'Website',
        route: 'website.dashboard',
        module: 'website',
        permission: 'website.view',
    },
    {
        label: 'Blog',
        route: 'website.blog.index',
        module: 'website',
        permission: 'website.view',
    },
];

/**
 * Resolves a route name, or null if Ziggy doesn't know it.
 *
 * `route()` throws on an unknown name, and this runs inside a render, so
 * one stale or renamed entry in PAGES would take down the whole page
 * rather than just omitting a row from a palette. Failing quietly is the
 * right trade here — a missing shortcut is a nuisance, a blank screen is
 * an outage.
 */
function safeRoute(name: string): string | null {
    try {
        return route(name);
    } catch {
        return null;
    }
}

export default function GlobalSearch() {
    const pageProps = usePage().props;
    // Defensive: this component renders inside the authenticated layout, so
    // `auth` is always shared — but a render-time crash here blanks the
    // entire app, which is too high a price for assuming it.
    //
    // Memoised because the `?? []` fallback would otherwise mint a new
    // array on every render and defeat the memo that depends on it.
    const permissions = useMemo(
        () => pageProps.auth?.permissions ?? [],
        [pageProps.auth?.permissions],
    );
    // Sections switched off for this business. Negative list, same rule as
    // the sidebar: hide only what we're told to.
    const hiddenModules = useMemo(
        () => pageProps.auth?.hiddenModules ?? [],
        [pageProps.auth?.hiddenModules],
    );
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState<SearchGroup[]>([]);
    const [activeIndex, setActiveIndex] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    // Bumped on every keystroke so a slow response for an old query can't
    // overwrite the results of a newer one — the classic race that makes a
    // search box flash stale results as you type.
    const requestId = useRef(0);

    const close = useCallback(() => {
        setOpen(false);
        setQuery('');
        setGroups([]);
        setError(null);
        setActiveIndex(0);
    }, []);

    useEffect(() => {
        const handler = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
                event.preventDefault();
                setOpen((previous) => !previous);
            } else if (event.key === 'Escape' && open) {
                close();
            }
        };

        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [open, close]);

    useEffect(() => {
        if (open) {
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open]);

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
            setGroups([]);
            setError(null);
            setLoading(false);
            return;
        }

        setLoading(true);
        const id = ++requestId.current;

        const timeout = setTimeout(async () => {
            try {
                const response = await window.axios.get(route('search'), {
                    params: { q: term },
                });

                if (id !== requestId.current) return;

                setGroups(response.data?.groups ?? []);
                setError(null);
                setActiveIndex(0);
            } catch {
                // Caught rather than left to reject: an unhandled rejection
                // shows nothing to the user and leaves the spinner running
                // forever, which reads as "search is broken and silent".
                if (id === requestId.current) {
                    setGroups([]);
                    setError('Search is unavailable right now.');
                }
            } finally {
                if (id === requestId.current) setLoading(false);
            }
        }, 200);

        return () => clearTimeout(timeout);
    }, [query]);

    // Page matches are computed locally and shown alongside the records.
    const pageMatches = useMemo<SearchResult[]>(() => {
        const term = query.trim().toLowerCase();

        if (term.length < 2) return [];

        return PAGES.filter(
            (entry) =>
                entry.label.toLowerCase().includes(term) &&
                (!entry.permission || permissions.includes(entry.permission)) &&
                // A page in a section the business doesn't have would 404.
                (!entry.module || !hiddenModules.includes(entry.module)),
        )
            .slice(0, 6)
            .flatMap((entry): SearchResult[] => {
                const url = safeRoute(entry.route);

                // flatMap rather than map+filter so the narrowing from
                // `string | null` to `string` happens naturally, instead of
                // needing a type predicate to assert it after the fact.
                return url === null
                    ? []
                    : [
                          {
                              id: `page:${entry.route}`,
                              type: 'Go to',
                              title: entry.label,
                              subtitle: null,
                              url,
                          },
                      ];
            });
    }, [query, permissions, hiddenModules]);

    const allGroups = useMemo<SearchGroup[]>(
        () =>
            pageMatches.length > 0
                ? [{ group: 'Go to', items: pageMatches }, ...groups]
                : groups,
        [pageMatches, groups],
    );

    // Flattened once so the arrow keys can walk the whole list without
    // caring which group a row belongs to.
    const flat = useMemo(
        () => allGroups.flatMap((group) => group.items),
        [allGroups],
    );

    const goTo = useCallback(
        (result: SearchResult) => {
            close();
            router.visit(result.url);
        },
        [close],
    );

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((previous) =>
                Math.min(previous + 1, flat.length - 1),
            );
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((previous) => Math.max(previous - 1, 0));
        } else if (event.key === 'Enter' && flat[activeIndex]) {
            event.preventDefault();
            goTo(flat[activeIndex]);
        }
    };

    // Running index across groups, so the highlighted row matches the one
    // Enter would open.
    let cursor = -1;

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="flex w-full items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-500 transition duration-150 ease-in-out hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
            >
                <MagnifyingGlassIcon className="h-4 w-4 shrink-0" />
                <span className="hidden flex-1 text-left sm:block">Search</span>
                <kbd className="hidden rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 text-xs text-gray-400 dark:border-gray-600 dark:bg-gray-800 sm:inline">
                    ⌘K
                </kbd>
            </button>

            <Modal show={open} maxWidth="lg" onClose={close}>
                <div className="flex items-center gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <MagnifyingGlassIcon className="h-5 w-5 shrink-0 text-gray-400" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="Search products, customers, invoices, pages…"
                        className="w-full border-0 bg-transparent text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:text-gray-100"
                    />
                    {loading && (
                        <span className="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-500" />
                    )}
                </div>

                <div className="max-h-96 overflow-y-auto">
                    {query.trim().length > 0 && query.trim().length < 2 && (
                        <p className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            Keep typing — at least two characters.
                        </p>
                    )}

                    {error && (
                        <p className="px-4 py-6 text-center text-sm text-red-600 dark:text-red-400">
                            {error}
                        </p>
                    )}

                    {!error &&
                        query.trim().length >= 2 &&
                        !loading &&
                        flat.length === 0 && (
                            <p className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nothing found for &ldquo;{query.trim()}&rdquo;.
                            </p>
                        )}

                    {allGroups.map((group) => (
                        <div key={group.group}>
                            <p className="sticky top-0 bg-gray-50 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                {group.group}
                            </p>

                            {group.items.map((result) => {
                                cursor += 1;
                                const index = cursor;

                                return (
                                    <button
                                        key={result.id}
                                        type="button"
                                        onClick={() => goTo(result)}
                                        onMouseEnter={() =>
                                            setActiveIndex(index)
                                        }
                                        className={`block w-full px-4 py-2.5 text-left text-sm transition-colors ${
                                            index === activeIndex
                                                ? 'bg-indigo-50 dark:bg-indigo-900/20'
                                                : ''
                                        }`}
                                    >
                                        <p className="font-medium text-gray-800 dark:text-gray-200">
                                            {result.title}
                                        </p>
                                        {result.subtitle && (
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {result.subtitle}
                                            </p>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    ))}
                </div>

                <div className="flex items-center gap-4 border-t border-gray-100 px-4 py-2 text-xs text-gray-400 dark:border-gray-700">
                    <span>↑↓ to move</span>
                    <span>↵ to open</span>
                    <span>esc to close</span>
                </div>
            </Modal>
        </>
    );
}
