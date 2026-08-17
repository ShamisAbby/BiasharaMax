import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useMemo,
    useState,
} from 'react';

const STORAGE_KEY = 'biasharaos-display-currency';

export interface DisplayCurrency {
    code: string;
    name: string;
    symbol: string;
    exchange_rate_to_base: string;
    is_base: boolean;
}

const CurrencyContext = createContext<{
    currency: DisplayCurrency | null;
    currencies: DisplayCurrency[];
    setCurrencyCode: (code: string) => void;
    /** Converts a base-currency (TZS) amount into the selected display currency and formats it. */
    formatMoney: (baseAmount: number) => string;
}>({
    currency: null,
    currencies: [],
    setCurrencyCode: () => {},
    formatMoney: (amount) => amount.toLocaleString(),
});

export function CurrencyProvider({ children }: PropsWithChildren) {
    const { availableCurrencies } = usePage<PageProps>().props;
    const currencies = availableCurrencies ?? [];

    const [code, setCode] = useState<string>(() => {
        if (typeof window === 'undefined') return '';
        return window.localStorage.getItem(STORAGE_KEY) ?? '';
    });

    const setCurrencyCode = useCallback((next: string) => {
        setCode(next);
        window.localStorage.setItem(STORAGE_KEY, next);
    }, []);

    const currency = useMemo(
        () =>
            currencies.find((c) => c.code === code) ??
            currencies.find((c) => c.is_base) ??
            currencies[0] ??
            null,
        [currencies, code],
    );

    const formatMoney = useCallback(
        (baseAmount: number) => {
            if (!currency) {
                return baseAmount.toLocaleString();
            }

            const rate = Number(currency.exchange_rate_to_base) || 1;
            const converted = currency.is_base ? baseAmount : baseAmount / rate;

            return `${currency.symbol} ${converted.toLocaleString(undefined, { maximumFractionDigits: currency.is_base ? 0 : 2 })}`;
        },
        [currency],
    );

    const value = useMemo(
        () => ({ currency, currencies, setCurrencyCode, formatMoney }),
        [currency, currencies, setCurrencyCode, formatMoney],
    );

    return (
        <CurrencyContext.Provider value={value}>
            {children}
        </CurrencyContext.Provider>
    );
}

export function useCurrency() {
    return useContext(CurrencyContext);
}
