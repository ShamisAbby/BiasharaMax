import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import {
    BellIcon,
    BellSlashIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';
import { Fragment, useState } from 'react';

export interface PlatformNotificationItem {
    id: string;
    type: string;
    severity: 'critical' | 'high' | 'medium' | 'low';
    title: string;
    description: string | null;
    href: string;
    created_at: string;
}

const SEVERITY_DOT: Record<string, string> = {
    critical: 'bg-red-500',
    high: 'bg-orange-500',
    medium: 'bg-amber-500',
    low: 'bg-gray-400',
};

/**
 * Notifications as a right-hand slide-over, matching the Filament panel.
 *
 * Was a small popover anchored under the bell. A drawer is the better
 * shape for this content regardless of matching: these items carry a
 * title, a description and two controls each, and a 320px dropdown
 * truncated every description to a single line — which for "Backup
 * failed on Aug 11" or a churn-risk reason list is the part that
 * actually says what happened.
 *
 * Headless UI's Dialog rather than a hand-rolled panel, so focus is
 * trapped, Escape closes, the page behind is inert, and the trigger gets
 * focus back on close. Those are easy to omit and very visible to anyone
 * navigating by keyboard.
 */
export default function BiNotificationBell({
    items,
    loaded,
    onDismiss,
    onDismissAll,
}: {
    items: PlatformNotificationItem[];
    loaded: boolean;
    /** Dismiss one item. Omit to render the list without controls. */
    onDismiss?: (item: PlatformNotificationItem) => void;
    onDismissAll?: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                aria-label={
                    items.length > 0
                        ? `Notifications, ${items.length} unread`
                        : 'Notifications'
                }
                className="relative inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
            >
                <BellIcon className="h-6 w-6" />
                {items.length > 0 && (
                    <span className="absolute right-1.5 top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                        {items.length > 9 ? '9+' : items.length}
                    </span>
                )}
            </button>

            <Transition show={open} as={Fragment}>
                <Dialog
                    onClose={() => setOpen(false)}
                    className="relative z-50"
                >
                    <TransitionChild
                        as={Fragment}
                        enter="ease-out duration-200"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="ease-in duration-150"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-[1px]" />
                    </TransitionChild>

                    <div className="fixed inset-0 overflow-hidden">
                        <div className="absolute inset-0 overflow-hidden">
                            <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full">
                                <TransitionChild
                                    as={Fragment}
                                    enter="transform transition ease-out duration-300"
                                    enterFrom="translate-x-full"
                                    enterTo="translate-x-0"
                                    leave="transform transition ease-in duration-200"
                                    leaveFrom="translate-x-0"
                                    leaveTo="translate-x-full"
                                >
                                    <DialogPanel className="pointer-events-auto flex w-screen max-w-md flex-col bg-white shadow-2xl dark:bg-gray-900">
                                        <div className="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                                            <div>
                                                <Dialog.Title className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                                    Notifications
                                                </Dialog.Title>
                                                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                    Live platform signals that
                                                    need attention
                                                </p>
                                            </div>

                                            <div className="flex shrink-0 items-center gap-3">
                                                {onDismissAll &&
                                                    items.length > 0 && (
                                                        <button
                                                            type="button"
                                                            onClick={
                                                                onDismissAll
                                                            }
                                                            className="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                                        >
                                                            Clear all
                                                        </button>
                                                    )}

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setOpen(false)
                                                    }
                                                    aria-label="Close notifications"
                                                    className="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                                >
                                                    <XMarkIcon className="h-5 w-5" />
                                                </button>
                                            </div>
                                        </div>

                                        <div className="flex-1 overflow-y-auto">
                                            {/*
                                              Three states, not two. "Loading"
                                              and "nothing to show" look
                                              identical if the empty state
                                              renders before the fetch
                                              resolves — and telling an
                                              operator there are no alerts
                                              when you have not looked yet is
                                              the wrong thing to say.
                                            */}
                                            {!loaded && (
                                                <p className="px-5 py-12 text-center text-sm text-gray-400">
                                                    Loading…
                                                </p>
                                            )}

                                            {loaded && items.length === 0 && (
                                                <div className="flex flex-col items-center px-5 py-16 text-center">
                                                    <span className="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                                        <BellSlashIcon className="h-7 w-7 text-gray-400 dark:text-gray-500" />
                                                    </span>
                                                    <p className="mt-4 text-base font-semibold text-gray-900 dark:text-gray-100">
                                                        No notifications
                                                    </p>
                                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                        Please check again
                                                        later.
                                                    </p>
                                                </div>
                                            )}

                                            {items.map((item) => (
                                                <div
                                                    key={item.id}
                                                    className="group flex items-start gap-3 border-b border-gray-50 px-5 py-4 transition last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50"
                                                >
                                                    <span
                                                        className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${SEVERITY_DOT[item.severity]}`}
                                                    />

                                                    <Link
                                                        href={item.href}
                                                        onClick={() =>
                                                            setOpen(false)
                                                        }
                                                        className="min-w-0 flex-1"
                                                    >
                                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            {item.title}
                                                        </p>
                                                        {item.description && (
                                                            <p className="mt-0.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                                                {
                                                                    item.description
                                                                }
                                                            </p>
                                                        )}
                                                    </Link>

                                                    {onDismiss && (
                                                        <button
                                                            type="button"
                                                            // Stops the click
                                                            // reaching the Link
                                                            // — dismissing and
                                                            // navigating at
                                                            // once would hide
                                                            // the item and then
                                                            // open the page
                                                            // about it.
                                                            onClick={(e) => {
                                                                e.preventDefault();
                                                                e.stopPropagation();
                                                                onDismiss(item);
                                                            }}
                                                            aria-label={`Dismiss: ${item.title}`}
                                                            title="Dismiss"
                                                            className="shrink-0 rounded p-1 text-gray-300 opacity-0 transition hover:bg-gray-200 hover:text-gray-600 focus:opacity-100 group-hover:opacity-100 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                                        >
                                                            <XMarkIcon className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </DialogPanel>
                                </TransitionChild>
                            </div>
                        </div>
                    </div>
                </Dialog>
            </Transition>
        </>
    );
}
