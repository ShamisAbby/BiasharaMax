import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { router } from '@inertiajs/react';
import { Fragment, useEffect, useState } from 'react';

interface SearchResult {
    type: string;
    id: string;
    title: string;
    subtitle: string | null;
    badge: string | null;
    href: string;
}

export default function BiCommandPalette({
    show,
    onClose,
}: {
    show: boolean;
    onClose: () => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!show) {
            setQuery('');
            setResults([]);
        }
    }, [show]);

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);
            return;
        }

        setLoading(true);
        const controller = new AbortController();

        const timeout = setTimeout(() => {
            fetch(route('platform.search', { q: query }), {
                signal: controller.signal,
            })
                .then((res) => res.json())
                .then((data) => setResults(data.results ?? []))
                .catch(() => {})
                .finally(() => setLoading(false));
        }, 200);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [query]);

    const select = (result: SearchResult | null) => {
        if (!result) return;
        onClose();
        router.visit(result.href);
    };

    return (
        <Transition show={show} as={Fragment} afterLeave={() => setQuery('')}>
            <Dialog onClose={onClose} className="relative z-50">
                <TransitionChild
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" />
                </TransitionChild>

                <div className="fixed inset-0 flex items-start justify-center px-4 pt-24">
                    <TransitionChild
                        enter="ease-out duration-200"
                        enterFrom="opacity-0 scale-95"
                        enterTo="opacity-100 scale-100"
                        leave="ease-in duration-150"
                        leaveFrom="opacity-100 scale-100"
                        leaveTo="opacity-0 scale-95"
                    >
                        <DialogPanel className="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-800">
                            <Combobox onChange={select}>
                                <div className="flex items-center gap-3 border-b border-gray-100 px-4 dark:border-gray-700">
                                    <MagnifyingGlassIcon className="h-5 w-5 text-gray-400" />
                                    <ComboboxInput
                                        autoFocus
                                        className="w-full border-none bg-transparent py-4 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:text-gray-100"
                                        placeholder="Search businesses, users, admins, subscriptions…"
                                        value={query}
                                        onChange={(e) =>
                                            setQuery(e.target.value)
                                        }
                                    />
                                    <kbd className="rounded border border-gray-200 px-1.5 py-0.5 text-xs text-gray-400 dark:border-gray-600">
                                        Esc
                                    </kbd>
                                </div>

                                <ComboboxOptions
                                    static
                                    className="max-h-80 overflow-y-auto p-2"
                                >
                                    {query.trim().length < 2 && (
                                        <p className="px-3 py-8 text-center text-sm text-gray-400">
                                            Type at least 2 characters to
                                            search.
                                        </p>
                                    )}
                                    {query.trim().length >= 2 &&
                                        !loading &&
                                        results.length === 0 && (
                                            <p className="px-3 py-8 text-center text-sm text-gray-400">
                                                No results for &ldquo;{query}
                                                &rdquo;.
                                            </p>
                                        )}
                                    {results.map((result) => (
                                        <ComboboxOption
                                            key={`${result.type}-${result.id}`}
                                            value={result}
                                            className="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2.5 text-sm data-[focus]:bg-indigo-50 dark:data-[focus]:bg-indigo-900/30"
                                        >
                                            <div>
                                                <p className="font-medium text-gray-900 dark:text-gray-100">
                                                    {result.title}
                                                </p>
                                                {result.subtitle && (
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        {result.subtitle}
                                                    </p>
                                                )}
                                            </div>
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                {result.type}
                                            </span>
                                        </ComboboxOption>
                                    ))}
                                </ComboboxOptions>
                            </Combobox>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
