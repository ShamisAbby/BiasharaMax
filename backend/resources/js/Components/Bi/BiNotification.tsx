import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XCircleIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import {
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useState,
} from 'react';

type ToastVariant = 'success' | 'error' | 'info' | 'warning';

type Toast = {
    id: number;
    message: string;
    variant: ToastVariant;
};

const ICONS: Record<ToastVariant, typeof CheckCircleIcon> = {
    success: CheckCircleIcon,
    error: XCircleIcon,
    info: InformationCircleIcon,
    warning: ExclamationTriangleIcon,
};

const COLORS: Record<ToastVariant, string> = {
    success: 'text-emerald-500',
    error: 'text-red-500',
    info: 'text-blue-500',
    warning: 'text-amber-500',
};

const ToastContext = createContext<{
    notify: (message: string, variant?: ToastVariant) => void;
}>({
    notify: () => {},
});

export function BiNotificationProvider({ children }: PropsWithChildren) {
    const [toasts, setToasts] = useState<Toast[]>([]);

    const notify = useCallback(
        (message: string, variant: ToastVariant = 'info') => {
            const id = Date.now() + Math.random();
            setToasts((previous) => [...previous, { id, message, variant }]);

            // Errors get longer on screen. A confirmation is a glance —
            // you already know what you did. An error has to be read,
            // understood, and acted on, and four seconds is not enough for
            // "these credentials do not match our records" if you are
            // mid-thought about which password you used.
            setTimeout(
                () =>
                    setToasts((previous) =>
                        previous.filter((t) => t.id !== id),
                    ),
                variant === 'error' ? 8000 : 4000,
            );
        },
        [],
    );

    const dismiss = (id: number) => {
        setToasts((previous) => previous.filter((t) => t.id !== id));
    };

    return (
        <ToastContext.Provider value={{ notify }}>
            {children}

            {/*
              A live region, not just a styled box. Anything that moves a
              message out of the form and into a corner has to announce
              itself, or it is invisible to a screen reader — an inline
              field error is read out automatically because it sits beside
              a labelled input; a floating div is not.
            */}
            <div
                aria-live="polite"
                aria-atomic="false"
                className="fixed bottom-4 right-4 z-[100] flex flex-col gap-2"
            >
                {toasts.map((toast) => {
                    const Icon = ICONS[toast.variant];

                    return (
                        <div
                            key={toast.id}
                            // `alert` interrupts; `status` waits its turn.
                            // An error is worth interrupting for, a success
                            // message is not.
                            role={
                                toast.variant === 'error' ? 'alert' : 'status'
                            }
                            className="flex items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                        >
                            <Icon
                                className={`h-5 w-5 shrink-0 ${COLORS[toast.variant]}`}
                            />
                            <p className="text-sm text-gray-700 dark:text-gray-200">
                                {toast.message}
                            </p>
                            <button
                                type="button"
                                onClick={() => dismiss(toast.id)}
                                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            >
                                <XMarkIcon className="h-4 w-4" />
                            </button>
                        </div>
                    );
                })}
            </div>
        </ToastContext.Provider>
    );
}

export function useBiNotification() {
    return useContext(ToastContext);
}
