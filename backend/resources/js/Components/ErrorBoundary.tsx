import {
    ArrowPathIcon,
    ClipboardDocumentIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';
import { Component, ErrorInfo, PropsWithChildren, ReactNode } from 'react';

interface State {
    error: Error | null;
    componentStack: string | null;
    copied: boolean;
}

/**
 * Catches render errors so one broken widget doesn't blank the whole app.
 *
 * React unmounts the entire tree when a render throws and nothing catches
 * it — the user gets a white page with no explanation and no way back
 * except retyping the URL. That is the worst possible failure mode for a
 * business tool: it looks like the data is gone.
 *
 * Must be a class. `componentDidCatch`/`getDerivedStateFromError` have no
 * hook equivalent; this is the one place React still requires one.
 */
export default class ErrorBoundary extends Component<
    PropsWithChildren<{ fallbackTitle?: string }>,
    State
> {
    state: State = { error: null, componentStack: null, copied: false };

    static getDerivedStateFromError(error: Error): Partial<State> {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        // Left on console rather than swallowed: without it a crash in
        // production is invisible to anyone trying to reproduce it.
        console.error('Unhandled render error', error, info.componentStack);

        this.setState({ componentStack: info.componentStack ?? null });
    }

    private copy = (): void => {
        const { error, componentStack } = this.state;

        void navigator.clipboard
            ?.writeText(
                [
                    error?.message ?? 'Unknown error',
                    error?.stack ?? '',
                    componentStack ?? '',
                ]
                    .filter(Boolean)
                    .join('\n\n'),
            )
            .then(() => {
                this.setState({ copied: true });
                window.setTimeout(() => this.setState({ copied: false }), 2000);
            });
    };

    private reset = (): void => {
        // A full reload rather than clearing the error state. Whatever
        // broke is usually in props handed down from the server, so
        // re-rendering the same tree would just throw again.
        window.location.reload();
    };

    render(): ReactNode {
        const { error, componentStack } = this.state;

        if (!error) {
            return this.props.children;
        }

        return (
            <div className="flex min-h-[60vh] items-center justify-center px-6 py-16">
                <div className="w-full max-w-md text-center">
                    <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
                        <ExclamationTriangleIcon className="h-6 w-6" />
                    </span>

                    <h1 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {this.props.fallbackTitle ?? 'This page didn’t load'}
                    </h1>

                    <p className="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                        Something went wrong while displaying this screen. Your
                        data is safe — nothing was changed.
                    </p>

                    {/*
                      Shown in production too, deliberately.
                      An error screen that says only "something went wrong"
                      forces whoever has to fix it to guess — which is
                      exactly what happened the first time this fired. This
                      is a business tool used by the people who own it, not
                      a public site where the message could tell an attacker
                      something useful.
                    */}
                    <pre className="mt-4 max-h-40 overflow-auto rounded-lg bg-gray-100 p-3 text-left text-xs leading-relaxed text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        {error.message || 'No error message was provided.'}
                    </pre>

                    {/*
                      The component stack, on screen rather than only in the
                      clipboard.
                      "Cannot read properties of undefined" names a property
                      and nothing else — not the file, not the component. On
                      its own it is almost unactionable: three separate
                      attempts were made to find one such crash by reading
                      the source, and each guessed a different wrong place.
                      The stack was captured the whole time and shown
                      nowhere.
                      The copy button is not enough on its own. It depends
                      on navigator.clipboard, which is unavailable over
                      plain HTTP and can be refused by permissions policy —
                      so the one path to the useful information could fail
                      silently, on exactly the screen where something has
                      already gone wrong.
                    */}
                    {componentStack && (
                        <details className="mt-2 text-left">
                            <summary className="cursor-pointer text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                Where it happened
                            </summary>
                            <pre className="mt-2 max-h-40 overflow-auto rounded-lg bg-gray-100 p-3 text-xs leading-relaxed text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                {componentStack.trim()}
                            </pre>
                        </details>
                    )}

                    <div className="mt-6 flex items-center justify-center gap-2">
                        <button
                            type="button"
                            onClick={this.reset}
                            className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
                        >
                            <ArrowPathIcon className="h-4 w-4" />
                            Reload the page
                        </button>

                        <button
                            type="button"
                            onClick={this.copy}
                            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            <ClipboardDocumentIcon className="h-4 w-4" />
                            {this.state.copied ? 'Copied' : 'Copy details'}
                        </button>
                    </div>
                </div>
            </div>
        );
    }
}
