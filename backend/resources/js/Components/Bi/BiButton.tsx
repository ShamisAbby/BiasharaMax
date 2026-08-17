import { ButtonHTMLAttributes, forwardRef } from 'react';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';
type Size = 'sm' | 'md';

const VARIANTS: Record<Variant, string> = {
    primary:
        'bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:ring-indigo-500 disabled:bg-indigo-300',
    secondary:
        'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus-visible:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500 disabled:bg-red-300',
    ghost: 'text-gray-600 hover:bg-gray-100 focus-visible:ring-indigo-500 dark:text-gray-300 dark:hover:bg-gray-700',
};

const SIZES: Record<Size, string> = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-sm',
};

const BiButton = forwardRef<
    HTMLButtonElement,
    ButtonHTMLAttributes<HTMLButtonElement> & {
        variant?: Variant;
        size?: Size;
    }
>(function BiButton(
    { variant = 'primary', size = 'md', className = '', ...props },
    ref,
) {
    return (
        <button
            ref={ref}
            {...props}
            className={`inline-flex items-center justify-center gap-2 rounded-lg font-medium transition duration-150 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 ${VARIANTS[variant]} ${SIZES[size]} ${className}`}
        />
    );
});

export default BiButton;
