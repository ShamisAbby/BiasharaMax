import { PropsWithChildren, ReactNode } from 'react';

export default function BiCard({
    title,
    description,
    actions,
    children,
    className = '',
    glass = false,
    padded = true,
}: PropsWithChildren<{
    title?: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
    /** Subtle frosted-glass treatment for hero/overview surfaces. */
    glass?: boolean;
    padded?: boolean;
}>) {
    return (
        <div
            // Joined, not concatenated. This used to end with
            // `hover:shadow-md' + className`, with no separator — so a
            // card given `lg:col-span-2` rendered
            // `hover:shadow-mdlg:col-span-2`, one token matching no rule,
            // and lost *both* classes.
            //
            // It failed quietly and only at wide viewports: below `lg`
            // every card is full width anyway, so the missing span
            // changed nothing on a laptop and left a column-wide gap down
            // the right of the dashboard on a large monitor. 57 call
            // sites pass a className here.
            className={[
                glass
                    ? 'border border-white/20 bg-white/70 backdrop-blur-xl dark:border-white/10 dark:bg-gray-900/60'
                    : 'bg-white ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700',
                'overflow-hidden rounded-2xl shadow-sm transition-shadow hover:shadow-md',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            {(title || actions) && (
                <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-gray-700/60">
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
            <div className={padded ? 'px-6 py-5' : ''}>{children}</div>
        </div>
    );
}
