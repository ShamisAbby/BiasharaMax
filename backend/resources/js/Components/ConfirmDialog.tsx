import Modal from '@/Components/Modal';
import {
    ExclamationTriangleIcon,
    InformationCircleIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';
import {
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';

export type ConfirmTone = 'danger' | 'warning' | 'info';

export interface ConfirmOptions {
    title: string;
    /** Say what will actually happen, not "are you sure?". */
    message?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: ConfirmTone;
    /**
     * Overrides the tone's default icon.
     *
     * Sign-out is the case this exists for: it is neither destructive nor a
     * warning, so none of the three tone icons describes it. Borrowing the
     * "info" circle for it would be vague where a door icon is instantly
     * legible.
     */
    icon?: typeof TrashIcon;
    onConfirm: () => void;
}

const TONES: Record<
    ConfirmTone,
    { icon: typeof TrashIcon; iconClass: string; button: string }
> = {
    danger: {
        icon: TrashIcon,
        iconClass:
            'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400',
        button: 'bg-red-600 hover:bg-red-500 focus-visible:outline-red-600',
    },
    warning: {
        icon: ExclamationTriangleIcon,
        iconClass:
            'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400',
        button: 'bg-amber-600 hover:bg-amber-500 focus-visible:outline-amber-600',
    },
    info: {
        icon: InformationCircleIcon,
        iconClass:
            'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300',
        button: 'bg-indigo-600 hover:bg-indigo-500 focus-visible:outline-indigo-600',
    },
};

const ConfirmContext = createContext<(options: ConfirmOptions) => void>(() => {
    throw new Error('useConfirm must be used inside <ConfirmDialogProvider>.');
});

/**
 * Replaces the browser's native `confirm()`, which cannot be styled and
 * renders as a bare OS dialog labelled with the site's host — visually
 * unrelated to the app, and jarring at the exact moment a user is being
 * asked to approve something destructive.
 *
 * Callback-based rather than promise-based on purpose: a drop-in for
 * `if (confirm('…')) { doThing() }` without forcing every event handler
 * that uses it to become `async`.
 */
export function ConfirmDialogProvider({ children }: PropsWithChildren) {
    const [options, setOptions] = useState<ConfirmOptions | null>(null);
    const confirmButtonRef = useRef<HTMLButtonElement>(null);

    const confirm = useCallback((next: ConfirmOptions) => setOptions(next), []);

    // Focus lands on the confirm button so the dialog is operable from the
    // keyboard immediately. Headless UI already traps focus and closes on
    // Escape, so Escape maps to cancel for free.
    useEffect(() => {
        if (options) {
            const id = window.setTimeout(
                () => confirmButtonRef.current?.focus(),
                50,
            );

            return () => window.clearTimeout(id);
        }
    }, [options]);

    const close = () => setOptions(null);

    const accept = () => {
        options?.onConfirm();
        close();
    };

    const tone = TONES[options?.tone ?? 'danger'];
    const Icon = options?.icon ?? tone.icon;

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}

            <Modal show={options !== null} onClose={close} maxWidth="md">
                {options && (
                    <div className="p-6">
                        <div className="flex gap-4">
                            <span
                                className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full ${tone.iconClass}`}
                            >
                                <Icon className="h-6 w-6" />
                            </span>

                            <div className="min-w-0 flex-1">
                                <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {options.title}
                                </h2>
                                {options.message && (
                                    <p className="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                                        {options.message}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                onClick={close}
                                className="inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                {options.cancelLabel ?? 'Cancel'}
                            </button>
                            <button
                                ref={confirmButtonRef}
                                type="button"
                                onClick={accept}
                                className={`inline-flex justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 ${tone.button}`}
                            >
                                {options.confirmLabel ?? 'Confirm'}
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </ConfirmContext.Provider>
    );
}

export function useConfirm() {
    return useContext(ConfirmContext);
}
