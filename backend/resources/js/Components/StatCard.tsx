import {
    ArrowDownRightIcon,
    ArrowUpRightIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';
import Sparkline from './Sparkline';

const SPARKLINE_TONE = {
    positive: { stroke: '#34D399', fill: 'rgba(52, 211, 153, 0.15)' },
    negative: { stroke: '#F87171', fill: 'rgba(248, 113, 113, 0.15)' },
    warning: { stroke: '#FBBF24', fill: 'rgba(251, 191, 36, 0.15)' },
};

export default function StatCard({
    icon,
    iconClassName = 'bg-indigo-600',
    title,
    value,
    delta,
    deltaTone = 'positive',
    href,
    badge,
    compareLabel,
    sparkline,
    footer,
}: {
    icon: ReactNode;
    iconClassName?: string;
    title: string;
    value: string | number;
    delta?: string;
    deltaTone?: 'positive' | 'negative' | 'warning';
    href?: string;
    badge?: ReactNode;
    /** When set, renders a "vs last week"-style row with `delta` as a trend pill instead of a plain line. */
    compareLabel?: string;
    /** Optional last-N-days series rendered as a small trend line at the bottom of the card. */
    sparkline?: number[];
    /** Muted explanatory line under the value — for a state whose consequence isn't obvious from the value alone. */
    footer?: ReactNode;
}) {
    const deltaColor = {
        positive: 'text-emerald-600 dark:text-emerald-400',
        negative: 'text-red-600 dark:text-red-400',
        warning: 'text-amber-600 dark:text-amber-400',
    }[deltaTone];

    const TrendIcon =
        deltaTone === 'negative' ? ArrowDownRightIcon : ArrowUpRightIcon;

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800/60 dark:ring-gray-700/80">
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
                {value}
            </p>
            {compareLabel ? (
                <div className="mt-2 flex items-center justify-between text-sm">
                    <span className="text-gray-400 dark:text-gray-500">
                        {compareLabel}
                    </span>
                    {delta && (
                        <span
                            className={`flex items-center gap-0.5 font-medium ${deltaColor}`}
                        >
                            <TrendIcon className="h-3.5 w-3.5" />
                            {delta}
                        </span>
                    )}
                </div>
            ) : (
                delta &&
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
                ))
            )}
            {footer && (
                <p className="mt-2 text-xs leading-relaxed text-gray-400 dark:text-gray-500">
                    {footer}
                </p>
            )}
            {sparkline && sparkline.length > 1 && (
                <div className="mt-3">
                    <Sparkline
                        data={sparkline}
                        {...SPARKLINE_TONE[deltaTone]}
                    />
                </div>
            )}
        </div>
    );
}
