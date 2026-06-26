import { PropsWithChildren } from 'react';

const VARIANTS = {
    neutral: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    success:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
    warning:
        'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
    danger: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    info: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
};

export default function Badge({
    variant = 'neutral',
    children,
}: PropsWithChildren<{ variant?: keyof typeof VARIANTS }>) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${VARIANTS[variant]}`}
        >
            {children}
        </span>
    );
}
