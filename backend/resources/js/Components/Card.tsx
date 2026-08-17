import { PropsWithChildren, ReactNode } from 'react';

export default function Card({
    title,
    description,
    actions,
    children,
    className = '',
}: PropsWithChildren<{
    title?: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
}>) {
    return (
        <div
            className={
                'overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800/60 dark:ring-gray-700/80 ' +
                className
            }
        >
            {(title || actions) && (
                <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-gray-700/80">
                    <div>
                        {title && (
                            <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                {title}
                            </h3>
                        )}
                        {description && (
                            <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions}
                </div>
            )}
            <div className="px-6 py-5">{children}</div>
        </div>
    );
}
