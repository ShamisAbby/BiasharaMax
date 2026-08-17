import BiAnimatedCounter from '@/Components/Bi/BiAnimatedCounter';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

export default function BiStatsCard({
    icon,
    iconClassName = 'bg-indigo-600',
    title,
    value,
    formatter,
    delta,
    deltaTone = 'positive',
    href,
    badge,
}: {
    icon: ReactNode;
    iconClassName?: string;
    title: string;
    value: number | string;
    formatter?: (value: number) => string;
    delta?: string;
    deltaTone?: 'positive' | 'negative' | 'warning' | 'neutral';
    href?: string;
    badge?: ReactNode;
}) {
    const deltaColor = {
        positive: 'text-emerald-600 dark:text-emerald-400',
        negative: 'text-red-600 dark:text-red-400',
        warning: 'text-amber-600 dark:text-amber-400',
        neutral: 'text-gray-500 dark:text-gray-400',
    }[deltaTone];

    return (
        <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition-shadow hover:shadow-md dark:bg-gray-800 dark:ring-gray-700">
            <div className="flex items-start justify-between">
                <span
                    className={`flex h-10 w-10 items-center justify-center rounded-lg text-white ${iconClassName}`}
                >
                    {icon}
                </span>
                {badge}
            </div>
            <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                {title}
            </p>
            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                {typeof value === 'number' ? (
                    <BiAnimatedCounter value={value} formatter={formatter} />
                ) : (
                    value
                )}
            </p>
            {delta &&
                (href ? (
                    <Link
                        href={href}
                        className={`mt-1 inline-block text-sm font-medium ${deltaColor} hover:underline`}
                    >
                        {delta}
                    </Link>
                ) : (
                    <p className={`mt-1 text-sm font-medium ${deltaColor}`}>
                        {delta}
                    </p>
                ))}
        </div>
    );
}
