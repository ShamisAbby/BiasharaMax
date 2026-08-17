import { useBiNotification } from '@/Components/Bi/BiNotification';
import { useEffect, useRef } from 'react';

/**
 * Surfaces a form's validation errors as toasts instead of inline text.
 *
 * Built for the sign-in screens, where the distinction matters: a failed
 * login is not really a problem with the email *field*. The server returns
 * "these credentials do not match our records" keyed to `email` because it
 * has to key it to something, but the message is about the attempt as a
 * whole. Pinning it under one input implies the other one is fine, which
 * is exactly what the server is careful not to say.
 *
 * Only use this where that reasoning holds. On an ordinary form — twelve
 * fields, three of them wrong — a toast is strictly worse than inline
 * errors: it names the problems but not their location, and it disappears
 * while you are still hunting for them.
 */
export function useFormErrorToasts(errors: Record<string, string | undefined>) {
    const { notify } = useBiNotification();

    // Inertia hands back a new errors object on every render, so comparing
    // by identity would re-fire the toast continuously. Comparing the
    // messages themselves means one toast per failed attempt — and a second
    // identical failure still announces, because the object is rebuilt.
    const lastShown = useRef<string | null>(null);

    useEffect(() => {
        const messages = Object.values(errors).filter(
            (message): message is string => Boolean(message),
        );

        if (messages.length === 0) {
            lastShown.current = null;

            return;
        }

        const signature = messages.join('|');

        if (signature === lastShown.current) return;

        lastShown.current = signature;

        // Deduped: several fields commonly carry the same message, and
        // three identical toasts stacked up read as three separate faults.
        [...new Set(messages)].forEach((message) => notify(message, 'error'));
    }, [errors, notify]);
}
