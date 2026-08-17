import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

export default function BiEmptyState({
    icon,
    title,
    description,
    actionLabel,
    actionHref,
}: {
    icon: ReactNode;
    title: string;
    description?: string;
    actionLabel?: string;
    actionHref?: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-12 text-center">
            <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500 dark:bg-indigo-900/30 dark:text-indigo-400">
                {icon}
            </span>
            <p className="mt-4 text-sm font-semibold text-gray-900 dark:text-gray-100">
                {title}
            </p>
            {description && (
                <p className="mt-1 max-w-xs text-sm text-gray-500 dark:text-gray-400">
                    {description}
                </p>
            )}
            {actionLabel && actionHref && (
                <Link
                    href={actionHref}
                    className="mt-4 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                >
                    {actionLabel}
                </Link>
            )}
        </div>
    );
}
