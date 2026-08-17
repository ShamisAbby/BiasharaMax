import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { PropsWithChildren, ReactNode } from 'react';

export default function BiModal({
    show,
    onClose,
    title,
    footer,
    maxWidth = 'md',
    children,
}: PropsWithChildren<{
    show: boolean;
    onClose: () => void;
    title?: string;
    footer?: ReactNode;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
}>) {
    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[maxWidth];

    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                className="fixed inset-0 z-50 flex transform items-center overflow-y-auto px-4 py-6 transition-all sm:px-0"
                onClose={onClose}
            >
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-900/50" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <DialogPanel
                        className={`mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all dark:bg-gray-800 sm:mx-auto sm:w-full ${maxWidthClass}`}
                    >
                        {title && (
                            <div className="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {title}
                                </h2>
                            </div>
                        )}
                        <div className="px-6 py-5">{children}</div>
                        {footer && (
                            <div className="flex justify-end gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-700">
                                {footer}
                            </div>
                        )}
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
