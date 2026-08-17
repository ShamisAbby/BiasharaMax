import { Locale, translations } from '@/lib/translations';
import {
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useMemo,
    useState,
} from 'react';

const STORAGE_KEY = 'biasharaos-locale';

function readInitialLocale(): Locale {
    if (typeof window === 'undefined') {
        return 'en';
    }
    const stored = window.localStorage.getItem(STORAGE_KEY);
    return stored === 'sw' ? 'sw' : 'en';
}

const LocaleContext = createContext<{
    locale: Locale;
    setLocale: (locale: Locale) => void;
    t: (key: string) => string;
}>({
    locale: 'en',
    setLocale: () => {},
    t: (key) => key,
});

export function LocaleProvider({ children }: PropsWithChildren) {
    const [locale, setLocaleState] = useState<Locale>(readInitialLocale);

    const setLocale = useCallback((next: Locale) => {
        setLocaleState(next);
        window.localStorage.setItem(STORAGE_KEY, next);
    }, []);

    const t = useCallback(
        (key: string) =>
            translations[locale][key] ?? translations.en[key] ?? key,
        [locale],
    );

    const value = useMemo(
        () => ({ locale, setLocale, t }),
        [locale, setLocale, t],
    );

    return (
        <LocaleContext.Provider value={value}>
            {children}
        </LocaleContext.Provider>
    );
}

export function useLocale() {
    return useContext(LocaleContext);
}
