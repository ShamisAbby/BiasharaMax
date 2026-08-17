import { useCallback, useState } from 'react';

const STORAGE_KEY = 'biasharaos-sidebar-collapsed-groups';

function read(): Record<string, boolean> {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        return raw ? JSON.parse(raw) : {};
    } catch {
        return {};
    }
}

function persist(state: Record<string, boolean>): void {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

/**
 * Persists which sidebar nav groups (by title) the user has collapsed,
 * so the sidebar remembers their preference across the full remount
 * every Inertia navigation causes here.
 */
export function useCollapsedNavGroups() {
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>(read);

    const toggle = useCallback((title: string) => {
        setCollapsed((previous) => {
            const next = { ...previous, [title]: !previous[title] };
            persist(next);

            return next;
        });
    }, []);

    return { collapsed, toggle };
}
