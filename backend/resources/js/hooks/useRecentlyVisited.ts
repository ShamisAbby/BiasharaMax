import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STORAGE_KEY = 'biasharaos-recently-visited';
const MAX_ITEMS = 5;

export interface RecentlyVisitedItem {
    url: string;
    title: string;
}

function read(): RecentlyVisitedItem[] {
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

/**
 * Tracks the last few pages visited, keyed by URL + the document title
 * Inertia's <Head> already set for that page — no guessing at labels,
 * just what the page itself declared. Persisted to localStorage so it
 * survives the full remount every Inertia navigation causes here (this
 * layout isn't a Persistent Layout).
 */
export function useRecentlyVisited(): RecentlyVisitedItem[] {
    const { url } = usePage();
    const [items, setItems] = useState<RecentlyVisitedItem[]>(read);

    useEffect(() => {
        const title = document.title;
        const next = [
            { url, title },
            ...read().filter((item) => item.url !== url),
        ].slice(0, MAX_ITEMS);
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        setItems(next);
        // Intentionally re-runs only when the URL changes, not on every render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url]);

    return items;
}
