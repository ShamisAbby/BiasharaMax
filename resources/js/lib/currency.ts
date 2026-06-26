/**
 * Formats a numeric amount (as returned by the API, typically a decimal
 * string) into a thousands-separated display value. Currency code/symbol
 * is rendered separately by the caller since plans can in principle be
 * priced in different currencies per business locale.
 */
export function formatCurrency(amount: string | number): string {
    return new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(
        Number(amount),
    );
}
