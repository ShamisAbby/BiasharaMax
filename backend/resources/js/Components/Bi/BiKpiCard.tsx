import BiAnimatedCounter from '@/Components/Bi/BiAnimatedCounter';
import BiSparkline from '@/Components/Bi/BiSparkline';
import {
    ArrowDownRightIcon,
    ArrowUpRightIcon,
} from '@heroicons/react/20/solid';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

export interface BiKpiMetric {
    value: number | string | null;
    trend?: number[] | null;
    change_percent?: number | null;
    today_change?: number;
}

export default function BiKpiCard({
    icon,
    iconClassName = 'bg-indigo-600',
    title,
    metric,
    formatter,
    href,
    invertTone = false,
}: {
    icon: ReactNode;
    iconClassName?: string;
    title: string;
    metric: BiKpiMetric;
    formatter?: (value: number) => string;
    href?: string;
    /** For metrics where "down" is good (e.g. failed jobs), flip the color logic. */
    invertTone?: boolean;
}) {
    const value = metric.value;
    const changePercent = metric.change_percent ?? null;
    const isUp = (changePercent ?? 0) >= 0;
    const tone =
        changePercent === null
            ? 'neutral'
            : isUp !== invertTone
              ? 'positive'
              : 'negative';

    const toneClasses = {
        positive: 'text-emerald-600 dark:text-emerald-400',
        negative: 'text-red-600 dark:text-red-400',
        neutral: 'text-gray-400 dark:text-gray-500',
    }[tone];

    const content = (
        <div className="group rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition-all hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-800 dark:ring-gray-700">
            <div className="flex items-start justify-between">
                <span
                    className={`flex h-10 w-10 items-center justify-center rounded-lg text-white ${iconClassName}`}
                >
                    {icon}
                </span>
                {metric.trend && metric.trend.length > 1 && (
                    <BiSparkline
                        data={metric.trend}
                        tone={tone === 'neutral' ? 'neutral' : tone}
                    />
                )}
            </div>

            <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                {title}
            </p>
            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                {typeof value === 'number' ? (
                    <BiAnimatedCounter value={value} formatter={formatter} />
                ) : (
                    (value ?? '—')
                )}
            </p>

            {changePercent !== null && (
                <p
                    className={`mt-1.5 flex items-center gap-1 text-sm font-medium ${toneClasses}`}
                >
                    {isUp ? (
                        <ArrowUpRightIcon className="h-4 w-4" />
                    ) : (
                        <ArrowDownRightIcon className="h-4 w-4" />
                    )}
                    {Math.abs(changePercent)}%
                    <span className="text-gray-400 dark:text-gray-500">
                        vs yesterday
                    </span>
                </p>
            )}
        </div>
    );

    return href ? <Link href={href}>{content}</Link> : content;
}
