import { useBiNotification } from '@/Components/Bi/BiNotification';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * Verbs that describe something being taken away or turned off.
 *
 * Status slugs follow a strict `{subject}-{verb}` shape across all 284 of
 * them, so the trailing verb is enough to pick a tone. Anything not listed
 * is an addition or a change, which reads as success.
 */
const CAUTIONARY_VERBS = new Set([
    'archived',
    'cancelled',
    'canceled',
    'closed',
    'deactivated',
    'deleted',
    'disabled',
    'disposed',
    'expired',
    'failed',
    'locked',
    'rejected',
    'removed',
    'reversed',
    'revoked',
    'suspended',
    'unpublished',
    'voided',
    'withdrawn',
]);

/**
 * Turns `supplier-created` into `Supplier created`.
 *
 * A lookup table would have needed 284 entries and would have gone stale
 * the first time someone added a controller. The slugs are mechanical
 * enough that deriving the sentence is both shorter and self-maintaining;
 * controllers that want specific wording use `->with('success', '...')`
 * instead, which is passed through untouched.
 */
function humanise(slug: string): string {
    const words = slug.replace(/[-_]+/g, ' ').trim();

    return words.charAt(0).toUpperCase() + words.slice(1);
}

function toneFor(slug: string): 'success' | 'warning' {
    const verb = slug.split(/[-_]/).pop() ?? '';

    return CAUTIONARY_VERBS.has(verb) ? 'warning' : 'success';
}

/**
 * Surfaces server flash messages as toasts.
 *
 * Mounted once in the authenticated layout rather than per page: the flash
 * bag is a shared prop, so every page already receives it, and doing this
 * in one place is what makes all ~320 `->with(...)` call sites work without
 * touching any of them.
 */
export function useFlashToasts() {
    const { notify } = useBiNotification();
    // `flash` is a globally shared prop (see PageProps), so no generic is
    // needed here — and passing a narrow one would fail the PageProps
    // constraint that the global Inertia augmentation applies.
    const flash = usePage().props.flash;

    // Inertia keeps props between partial reloads, so the same flash object
    // can be handed to us more than once. Keying on the page's unique
    // request id would be ideal, but it isn't exposed — comparing the
    // serialised bag is enough to stop a message repeating on every render
    // while still letting an identical message fire again on a later visit.
    const lastShown = useRef<string | null>(null);

    useEffect(() => {
        if (!flash) return;

        const messages: Array<{
            text: string;
            tone: 'success' | 'warning' | 'error' | 'info';
        }> = [];

        // Explicit, human-written messages win — a controller that bothered
        // to write a sentence knows better than the slug humaniser.
        if (typeof flash.success === 'string' && flash.success) {
            messages.push({ text: flash.success, tone: 'success' });
        }

        if (typeof flash.error === 'string' && flash.error) {
            messages.push({ text: flash.error, tone: 'error' });
        }

        if (typeof flash.warning === 'string' && flash.warning) {
            messages.push({ text: flash.warning, tone: 'warning' });
        }

        if (typeof flash.info === 'string' && flash.info) {
            messages.push({ text: flash.info, tone: 'info' });
        }

        if (typeof flash.status === 'string' && flash.status) {
            messages.push({
                text: humanise(flash.status),
                tone: toneFor(flash.status),
            });
        }

        if (messages.length === 0) return;

        const signature = JSON.stringify(messages);
        if (signature === lastShown.current) return;
        lastShown.current = signature;

        messages.forEach((message) => notify(message.text, message.tone));
    }, [flash, notify]);
}
