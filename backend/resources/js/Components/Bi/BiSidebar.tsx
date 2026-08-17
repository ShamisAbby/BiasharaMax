import {
    ChevronDownIcon,
    MagnifyingGlassIcon,
    StarIcon as StarOutlineIcon,
} from '@heroicons/react/24/outline';
import { StarIcon as StarSolidIcon } from '@heroicons/react/24/solid';
import { Link } from '@inertiajs/react';
import { ReactNode, useEffect, useMemo, useState } from 'react';

export interface BiSidebarItem {
    key: string;
    label: string;
    icon: ReactNode;
    href?: string;
    /** Wildcard route-name pattern for active-state matching, when a single item should highlight for several routes (e.g. a tabbed sub-section). Defaults to `href + '*'`. */
    activePattern?: string;
    active?: boolean;
    soon?: boolean;
    badge?: ReactNode;
}

export interface BiSidebarGroup {
    key: string;
    label?: string;
    items: BiSidebarItem[];
}

const FAVORITES_KEY = 'biasharaos-admin-sidebar-favorites';
const RECENTS_KEY = 'biasharaos-admin-sidebar-recents';
const COLLAPSED_SECTIONS_KEY = 'biasharaos-admin-sidebar-collapsed-sections';
const MAX_RECENTS = 5;

function readJson<T>(key: string, fallback: T): T {
    if (typeof window === 'undefined') return fallback;
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? (JSON.parse(raw) as T) : fallback;
    } catch {
        return fallback;
    }
}

function writeJson(key: string, value: unknown): void {
    window.localStorage.setItem(key, JSON.stringify(value));
}

export default function BiSidebar({
    groups,
    collapsed = false,
    header,
    footer,
}: {
    groups: BiSidebarGroup[];
    collapsed?: boolean;
    header?: ReactNode;
    footer?: ReactNode;
}) {
    const [search, setSearch] = useState('');
    const [favorites, setFavorites] = useState<string[]>(() =>
        readJson(FAVORITES_KEY, []),
    );
    const [recentKeys, setRecentKeys] = useState<string[]>(() =>
        readJson(RECENTS_KEY, []),
    );
    const [collapsedSections, setCollapsedSections] = useState<
        Record<string, boolean>
    >(() => readJson(COLLAPSED_SECTIONS_KEY, {}));

    const allItems = useMemo(() => groups.flatMap((g) => g.items), [groups]);

    const toggleFavorite = (key: string) => {
        setFavorites((previous) => {
            const next = previous.includes(key)
                ? previous.filter((k) => k !== key)
                : [...previous, key];
            writeJson(FAVORITES_KEY, next);
            return next;
        });
    };

    const recordVisit = (key: string) => {
        setRecentKeys((previous) => {
            const next = [key, ...previous.filter((k) => k !== key)].slice(
                0,
                MAX_RECENTS,
            );
            writeJson(RECENTS_KEY, next);
            return next;
        });
    };

    const toggleSection = (key: string) => {
        setCollapsedSections((previous) => {
            const next = { ...previous, [key]: !previous[key] };
            writeJson(COLLAPSED_SECTIONS_KEY, next);
            return next;
        });
    };

    // If the active route belongs to a collapsed section, auto-expand it so
    // the current page is never hidden behind a collapsed group.
    useEffect(() => {
        const activeGroup = groups.find((g) => g.items.some((i) => i.active));
        if (activeGroup && collapsedSections[activeGroup.key]) {
            setCollapsedSections((previous) => {
                const next = { ...previous, [activeGroup.key]: false };
                writeJson(COLLAPSED_SECTIONS_KEY, next);
                return next;
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const favoriteItems = allItems.filter((item) =>
        favorites.includes(item.key),
    );
    const recentItems = recentKeys
        .map((key) => allItems.find((item) => item.key === key))
        .filter((item): item is BiSidebarItem => Boolean(item));

    const term = search.trim().toLowerCase();
    const filteredGroups = term
        ? groups
              .map((group) => ({
                  ...group,
                  items: group.items.filter((item) =>
                      item.label.toLowerCase().includes(term),
                  ),
              }))
              .filter((group) => group.items.length > 0)
        : groups;

    const renderItem = (
        item: BiSidebarItem,
        options?: { showStar?: boolean },
    ) =>
        item.soon || !item.href ? (
            <div
                key={item.key}
                title={collapsed ? item.label : undefined}
                className="flex cursor-not-allowed items-center justify-between rounded-md px-3 py-2 text-sm text-gray-400 dark:text-gray-600"
            >
                <span className="flex items-center gap-3">
                    <span className="h-5 w-5 shrink-0">{item.icon}</span>
                    {!collapsed && item.label}
                </span>
                {!collapsed && item.soon && (
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                        Soon
                    </span>
                )}
            </div>
        ) : (
            <Link
                key={item.key}
                href={item.href}
                onClick={() => recordVisit(item.key)}
                title={collapsed ? item.label : undefined}
                className={`group relative flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition duration-150 ease-in-out ${
                    item.active
                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100'
                }`}
            >
                {item.active && (
                    <span className="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-indigo-600 dark:bg-indigo-400" />
                )}
                <span className="h-5 w-5 shrink-0">{item.icon}</span>
                {!collapsed && (
                    <span className="flex-1 truncate">{item.label}</span>
                )}
                {!collapsed && item.badge}
                {!collapsed && options?.showStar && (
                    <button
                        type="button"
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            toggleFavorite(item.key);
                        }}
                        className="opacity-0 transition group-hover:opacity-100"
                        aria-label="Toggle favorite"
                    >
                        {favorites.includes(item.key) ? (
                            <StarSolidIcon className="h-4 w-4 text-amber-400" />
                        ) : (
                            <StarOutlineIcon className="h-4 w-4 text-gray-300 hover:text-amber-400" />
                        )}
                    </button>
                )}
            </Link>
        );

    return (
        <>
            {header}

            {!collapsed && (
                <div className="px-3 pb-3">
                    <div className="relative">
                        <MagnifyingGlassIcon className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search navigation…"
                            className="w-full rounded-md border-gray-200 bg-gray-50 py-1.5 pl-8 pr-3 text-sm placeholder:text-gray-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:focus:bg-gray-900"
                        />
                    </div>
                </div>
            )}

            <nav className="flex-1 space-y-5 overflow-y-auto px-3 pb-6">
                {!term && favoriteItems.length > 0 && (
                    <div className="space-y-1">
                        {!collapsed && (
                            <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                Favorites
                            </p>
                        )}
                        {favoriteItems.map((item) =>
                            renderItem(item, { showStar: true }),
                        )}
                    </div>
                )}

                {filteredGroups.map((group, groupIndex) => (
                    <div
                        key={group.key}
                        className={
                            groupIndex > 0 || favoriteItems.length > 0
                                ? 'space-y-1 border-t border-gray-100 pt-4 dark:border-gray-700'
                                : 'space-y-1'
                        }
                    >
                        {group.label && !collapsed && (
                            <button
                                type="button"
                                onClick={() => toggleSection(group.key)}
                                className="flex w-full items-center justify-between px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                            >
                                {group.label}
                                <ChevronDownIcon
                                    className={`h-3.5 w-3.5 transition-transform ${collapsedSections[group.key] ? '-rotate-90' : ''}`}
                                />
                            </button>
                        )}

                        {!(collapsedSections[group.key] && !collapsed) &&
                            group.items.map((item) =>
                                renderItem(item, { showStar: true }),
                            )}
                    </div>
                ))}

                {term && filteredGroups.length === 0 && (
                    <p className="px-3 py-6 text-center text-sm text-gray-400">
                        No matches for &ldquo;{search}&rdquo;.
                    </p>
                )}

                {!term && recentItems.length > 0 && (
                    <div className="space-y-0.5 border-t border-gray-100 pt-4 dark:border-gray-700">
                        {!collapsed && (
                            <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                Recently Visited
                            </p>
                        )}
                        {recentItems.map((item) => renderItem(item))}
                    </div>
                )}
            </nav>

            {!collapsed && footer}
        </>
    );
}
