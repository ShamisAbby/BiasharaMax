import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'biasharaos-theme';

function readInitialPreference(): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    // app.blade.php already applied the class before hydration; just read it
    // back so React's state matches the DOM instead of guessing again.
    return document.documentElement.classList.contains('dark');
}

/**
 * Single source of truth for dark mode: persists to localStorage and
 * toggles the `dark` class Tailwind's `darkMode: 'class'` strategy reads.
 */
export function useDarkMode() {
    const [isDark, setIsDark] = useState(readInitialPreference);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', isDark);
        localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
    }, [isDark]);

    const toggle = useCallback(() => setIsDark((previous) => !previous), []);

    return { isDark, toggle };
}
