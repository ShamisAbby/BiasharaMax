import TextInput from '@/Components/TextInput';
import { EyeIcon, EyeSlashIcon } from '@heroicons/react/24/outline';
import { InputHTMLAttributes, useEffect, useState } from 'react';

/**
 * A password field with a reveal toggle.
 *
 * Typing a password you can't see is the single most common reason people
 * fail to sign in, and it's worst exactly where it matters most — on a
 * phone keyboard, or with a long generated password. The toggle costs
 * nothing and removes most of that.
 *
 * The details that are easy to get wrong, and why each one is here:
 *
 * - `type="button"`. A bare `<button>` inside a form defaults to
 *   `type="submit"`, so without this the eye icon would submit the login
 *   form instead of revealing anything.
 * - The label changes with the state and is announced. A static
 *   "toggle password" tells a screen-reader user nothing about which way
 *   it is currently set; `aria-pressed` carries that.
 * - Padding on the input, not a floating icon. Text must never run
 *   underneath the button, including at the end of a long password.
 * - It hides itself again whenever the field is emptied — which is what a
 *   failed login does — so a visible password can't outlive the attempt
 *   that revealed it and sit on screen afterwards.
 */
export default function PasswordInput({
    className = '',
    value,
    isFocused = false,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & { isFocused?: boolean }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (value === '') {
            setVisible(false);
        }
    }, [value]);

    return (
        <div className={`relative ${className}`}>
            <TextInput
                {...props}
                value={value}
                isFocused={isFocused}
                type={visible ? 'text' : 'password'}
                // Logical padding, so the reserved space follows the text
                // direction rather than always sitting on the right.
                className="block w-full pe-11"
            />

            <button
                type="button"
                onClick={() => setVisible((shown) => !shown)}
                aria-label={visible ? 'Hide password' : 'Show password'}
                aria-pressed={visible}
                // Not in the tab order: a keyboard user tabbing from the
                // password field expects to land on the submit button, and
                // this is reachable without it. Still fully operable by
                // mouse, touch and screen-reader gesture.
                tabIndex={-1}
                className="absolute inset-y-0 end-0 flex items-center pe-3 text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
            >
                {visible ? (
                    <EyeSlashIcon className="h-5 w-5" aria-hidden="true" />
                ) : (
                    <EyeIcon className="h-5 w-5" aria-hidden="true" />
                )}
            </button>
        </div>
    );
}
