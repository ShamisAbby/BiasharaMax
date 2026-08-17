import {
    ChatBubbleLeftRightIcon,
    PaperAirplaneIcon,
    SparklesIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { FormEvent, useEffect, useState } from 'react';

interface Message {
    role: 'user' | 'assistant';
    text: string;
    source?: string;
}

const SUGGESTED_QUESTIONS = [
    'Which products should I reorder?',
    'Who owes me money?',
    'Which suppliers should I pay first?',
    'Why did profit decrease?',
];

/** Lets any component (e.g. the topbar's "Ask AI" button) open the assistant without prop-drilling. */
export const OPEN_BUSINESS_ASSISTANT_EVENT =
    'biasharaos:open-business-assistant';

export default function BusinessAssistant() {
    const [open, setOpen] = useState(false);
    const [question, setQuestion] = useState('');
    const [messages, setMessages] = useState<Message[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const handler = () => setOpen(true);
        window.addEventListener(OPEN_BUSINESS_ASSISTANT_EVENT, handler);
        return () =>
            window.removeEventListener(OPEN_BUSINESS_ASSISTANT_EVENT, handler);
    }, []);

    const ask = async (text: string) => {
        if (text.trim() === '' || loading) return;

        setMessages((previous) => [...previous, { role: 'user', text }]);
        setQuestion('');
        setLoading(true);

        try {
            const response = await window.axios.post(route('assistant.ask'), {
                question: text,
            });
            setMessages((previous) => [
                ...previous,
                {
                    role: 'assistant',
                    text: response.data.answer,
                    source: response.data.source,
                },
            ]);
        } catch {
            setMessages((previous) => [
                ...previous,
                {
                    role: 'assistant',
                    text: 'Something went wrong asking that — please try again.',
                },
            ]);
        } finally {
            setLoading(false);
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        ask(question);
    };

    return (
        <div className="fixed bottom-5 end-5 z-50">
            {open && (
                <div className="mb-3 flex h-96 w-80 flex-col overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <div className="flex items-center justify-between bg-indigo-600 px-4 py-3 text-white">
                        <span className="flex items-center gap-2 text-sm font-semibold">
                            <SparklesIcon className="h-5 w-5" /> Business
                            Assistant
                        </span>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            aria-label="Close assistant"
                        >
                            <XMarkIcon className="h-5 w-5" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-3 overflow-y-auto p-3">
                        {messages.length === 0 && (
                            <div className="space-y-2">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Ask me about restocking, customer debts,
                                    supplier payments, slow-moving products, or
                                    your profit trend — every answer is pulled
                                    from your real data.
                                </p>
                                {SUGGESTED_QUESTIONS.map((suggestion) => (
                                    <button
                                        key={suggestion}
                                        type="button"
                                        onClick={() => ask(suggestion)}
                                        className="block w-full rounded-md bg-gray-50 px-3 py-2 text-start text-sm text-indigo-700 hover:bg-gray-100 dark:bg-gray-900/40 dark:text-indigo-300 dark:hover:bg-gray-900"
                                    >
                                        {suggestion}
                                    </button>
                                ))}
                            </div>
                        )}

                        {messages.map((message, index) => (
                            <div
                                key={index}
                                className={`max-w-[85%] rounded-lg px-3 py-2 text-sm ${
                                    message.role === 'user'
                                        ? 'ms-auto bg-indigo-600 text-white'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-900/60 dark:text-gray-200'
                                }`}
                            >
                                {message.text}
                                {message.source &&
                                    message.source !== 'declined' && (
                                        <p className="mt-1 text-[10px] uppercase tracking-wide text-gray-400">
                                            Source: {message.source}
                                        </p>
                                    )}
                            </div>
                        ))}

                        {loading && (
                            <div className="max-w-[85%] rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-400 dark:bg-gray-900/60">
                                Thinking…
                            </div>
                        )}
                    </div>

                    <form
                        onSubmit={submit}
                        className="flex items-center gap-2 border-t border-gray-100 p-3 dark:border-gray-700"
                    >
                        <input
                            value={question}
                            onChange={(e) => setQuestion(e.target.value)}
                            placeholder="Ask a question..."
                            className="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        />
                        <button
                            type="submit"
                            disabled={loading}
                            className="rounded-md bg-indigo-600 p-2 text-white hover:bg-indigo-700 disabled:opacity-50"
                            aria-label="Send"
                        >
                            <PaperAirplaneIcon className="h-4 w-4" />
                        </button>
                    </form>
                </div>
            )}

            <button
                type="button"
                onClick={() => setOpen((previous) => !previous)}
                aria-label="Open business assistant"
                className="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-lg transition hover:bg-indigo-700"
            >
                {open ? (
                    <XMarkIcon className="h-6 w-6" />
                ) : (
                    <ChatBubbleLeftRightIcon className="h-6 w-6" />
                )}
            </button>
        </div>
    );
}
