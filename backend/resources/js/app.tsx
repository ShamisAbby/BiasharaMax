import '../css/app.css';
import './bootstrap';

import { BiNotificationProvider } from '@/Components/Bi/BiNotification';
import { ConfirmDialogProvider } from '@/Components/ConfirmDialog';
import ErrorBoundary from '@/Components/ErrorBoundary';
import { LocaleProvider } from '@/contexts/LocaleContext';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <LocaleProvider>
                <BiNotificationProvider>
                    {/*
                      Mounted once at the root so any page can raise a
                      styled confirmation via useConfirm() without each
                      one owning its own modal state.
                    */}
                    <ConfirmDialogProvider>
                        {/*
                          Inside the providers, not outside: a crash should
                          still be able to render the toast and dialog
                          containers, and the fallback needs the same theme
                          context as everything else.
                        */}
                        <ErrorBoundary>
                            <App {...props} />
                        </ErrorBoundary>
                    </ConfirmDialogProvider>
                </BiNotificationProvider>
            </LocaleProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
