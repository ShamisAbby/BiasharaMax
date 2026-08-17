import { useCallback, useState } from 'react';

const STORAGE_KEY = 'biasharaos-sidebar-favorites';

function read(): string[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function persist(routeNames: string[]): void {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(routeNames));
}

/**
 * Lets a user pin nav items by route name; persisted to localStorage
 * (per-browser, not per-account — there's no backend model for this and
 * it doesn't need one).
 */
export function useSidebarFavorites() {
    const [favorites, setFavorites] = useState<string[]>(read);

    const toggle = useCallback((routeName: string) => {
        setFavorites((previous) => {
            const next = previous.includes(routeName)
                ? previous.filter((name) => name !== routeName)
                : [...previous, routeName];
            persist(next);

            return next;
        });
    }, []);

    return { favorites, toggle };
}
