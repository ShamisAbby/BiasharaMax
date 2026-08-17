import { useConfirm } from '@/Components/ConfirmDialog';
import { ArrowRightStartOnRectangleIcon } from '@heroicons/react/24/outline';
import { router } from '@inertiajs/react';
import { useCallback } from 'react';

/**
 * Confirms before signing out.
 *
 * One hook for both dashboards so the wording and the tone can't drift
 * apart — a vendor and a Super Admin should not get two different-looking
 * answers to the same question.
 *
 * Deliberately **not** styled as a destructive action. Signing out is
 * reversible in about five seconds; dressing it in the same red as "delete
 * every record in this business" is how people learn to click through red
 * dialogs without reading them. It uses a neutral tone and a door icon, and
 * spends its one sentence on the thing that is actually at stake — work in
 * progress that hasn't been saved.
 */
export function useSignOutConfirm() {
    const confirm = useConfirm();

    return useCallback(
        (options: { routeName: string; name?: string | null }) => {
            confirm({
                title: options.name
                    ? `Sign out, ${options.name.split(' ')[0]}?`
                    : 'Sign out?',
                message:
                    'Anything you have typed but not saved will be lost. You can sign back in at any time.',
                confirmLabel: 'Sign out',
                cancelLabel: 'Stay signed in',
                tone: 'info',
                icon: ArrowRightStartOnRectangleIcon,
                onConfirm: () => router.post(route(options.routeName)),
            });
        },
        [confirm],
    );
}
