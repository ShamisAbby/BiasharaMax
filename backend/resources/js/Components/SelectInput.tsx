import { ChevronDownIcon } from '@heroicons/react/20/solid';
import { forwardRef, SelectHTMLAttributes } from 'react';

export default forwardRef(function SelectInput(
    {
        className = '',
        children,
        ...props
    }: SelectHTMLAttributes<HTMLSelectElement>,
    ref: React.Ref<HTMLSelectElement>,
) {
    return (
        <div className={`relative ${className}`}>
            <select
                {...props}
                className={[
                    // Reset native arrow
                    'appearance-none',
                    // Layout — fill the wrapper, extra right padding for chevron
                    'block w-full py-2 pl-3 pr-9',
                    // Typography
                    'text-sm text-gray-900 dark:text-gray-100',
                    // Border & background
                    'rounded-lg border border-gray-300 bg-white shadow-sm',
                    'dark:border-gray-600 dark:bg-gray-800',
                    // Focus ring
                    'focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20',
                    'dark:focus:border-indigo-500 dark:focus:ring-indigo-500/20',
                    // Disabled state
                    'disabled:cursor-not-allowed disabled:bg-gray-50 disabled:opacity-50 dark:disabled:bg-gray-900',
                    // Transition
                    'transition-colors duration-150',
                ].join(' ')}
                ref={ref}
            >
                {children}
            </select>

            {/* Custom chevron — pointer-events-none so clicks pass through to <select> */}
            <span className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
                <ChevronDownIcon
                    className="h-4 w-4 text-gray-400 dark:text-gray-500"
                    aria-hidden
                />
            </span>
        </div>
    );
});
