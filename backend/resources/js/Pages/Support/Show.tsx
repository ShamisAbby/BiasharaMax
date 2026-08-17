import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    ArrowLeftIcon,
    LifebuoyIcon,
    LockClosedIcon,
} from '@heroicons/react/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Message {
    id: string;
    body: string;
    from_support: boolean;
    created_at: string;
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    open: 'warning',
    pending: 'warning',
    in_progress: 'info',
    reopened: 'danger',
    resolved: 'success',
    closed: 'neutral',
};

const STATUS_LABEL: Record<string, string> = {
    open: 'Open',
    pending: 'Awaiting reply',
    in_progress: 'In progress',
    reopened: 'Reopened',
    resolved: 'Resolved',
    closed: 'Closed',
};

const PRIORITY_LABEL: Record<string, string> = {
    low: 'Low priority',
    medium: 'Medium priority',
    high: 'High priority',
    urgent: 'Urgent',
};

const CATEGORY_LABEL: Record<string, string> = {
    technical: 'Technical problem',
    billing: 'Billing or subscription',
    account: 'Account and access',
    feature_request: 'Feature request',
    other: 'Something else',
};

export default function SupportShow({
    ticket,
    messages,
}: {
    ticket: {
        id: string;
        ticket_number: string;
        subject: string;
        description: string;
        category: string;
        priority: string;
        status: string;
        created_at: string;
        resolved_at: string | null;
        closed_at: string | null;
    };
    messages: Message[];
}) {
    const confirm = useConfirm();
    const form = useForm({ body: '' });

    const isClosed = ticket.status === 'closed';

    const submit = (e: FormEvent) => {
        e.preventDefault();

        form.post(route('support.reply', ticket.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        });
    };

    const close = () => {
        confirm({
            title: 'Close this ticket?',
            message:
                'You will not be able to reply afterwards. If the problem comes back, open a new ticket and mention this number.',
            confirmLabel: 'Close ticket',
            cancelLabel: 'Keep it open',
            tone: 'warning',
            onConfirm: () =>
                router.post(
                    route('support.close', ticket.id),
                    {},
                    { preserveScroll: true },
                ),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={ticket.subject} />

            {/*
              Constrained and centred. This page previously had no
              container at all, so it ran the full width of the screen —
              which put a two-word reply alone in a band a metre wide and
              made the thread almost unreadable. A conversation wants a
              column, not a table's width.
            */}
            <div className="py-8">
                <div className="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('support.index')}
                        className="inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <ArrowLeftIcon className="h-4 w-4" />
                        All tickets
                    </Link>

                    <div className="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="min-w-0">
                                <h1 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {ticket.subject}
                                </h1>
                                <p className="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {ticket.ticket_number}
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-3">
                                <BiBadge
                                    variant={
                                        STATUS_VARIANT[ticket.status] ??
                                        'neutral'
                                    }
                                >
                                    {STATUS_LABEL[ticket.status] ??
                                        ticket.status}
                                </BiBadge>

                                {!isClosed && (
                                    <SecondaryButton
                                        type="button"
                                        onClick={close}
                                    >
                                        Close ticket
                                    </SecondaryButton>
                                )}
                            </div>
                        </div>

                        {/*
                          Category and priority were captured when the
                          ticket was raised and then never shown back. A
                          customer who marked something Urgent should be
                          able to see that it was recorded as urgent —
                          otherwise the field feels like it went nowhere.
                        */}
                        <dl className="mt-5 grid gap-4 border-t border-gray-100 pt-4 text-sm dark:border-gray-700 sm:grid-cols-3">
                            <Fact label="Category">
                                {CATEGORY_LABEL[ticket.category] ??
                                    ticket.category}
                            </Fact>
                            <Fact label="Priority">
                                {PRIORITY_LABEL[ticket.priority] ??
                                    ticket.priority}
                            </Fact>
                            <Fact label="Opened">
                                {new Date(ticket.created_at).toLocaleDateString(
                                    undefined,
                                    {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    },
                                )}
                            </Fact>
                        </dl>
                    </div>

                    <div className="space-y-4">
                        <Bubble fromSupport={false} at={ticket.created_at}>
                            {ticket.description}
                        </Bubble>

                        {messages.map((message) => (
                            <Bubble
                                key={message.id}
                                fromSupport={message.from_support}
                                at={message.created_at}
                            >
                                {message.body}
                            </Bubble>
                        ))}

                        {ticket.status === 'resolved' && (
                            <p className="py-2 text-center text-xs text-gray-500 dark:text-gray-400">
                                Support marked this resolved. Still having the
                                problem? Reply below and it reopens.
                            </p>
                        )}
                    </div>

                    {isClosed ? (
                        <div className="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
                            <LockClosedIcon className="mt-0.5 h-4 w-4 shrink-0" />
                            <p>
                                This ticket is closed. If the problem comes
                                back, open a new one and mention{' '}
                                <span className="font-mono">
                                    {ticket.ticket_number}
                                </span>
                                .
                            </p>
                        </div>
                    ) : (
                        <form
                            onSubmit={submit}
                            className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <textarea
                                rows={3}
                                className="block w-full resize-none rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                value={form.data.body}
                                onChange={(e) =>
                                    form.setData('body', e.target.value)
                                }
                                placeholder="Write a reply…"
                            />
                            <InputError
                                message={form.errors.body}
                                className="mt-2"
                            />

                            <div className="mt-3 flex items-center justify-between gap-3">
                                <p className="text-xs text-gray-400">
                                    We usually reply within one working day.
                                </p>
                                <BiButton
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        form.data.body.trim() === ''
                                    }
                                >
                                    Send reply
                                </BiButton>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Fact({ label, children }: { label: string; children: string }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-gray-400">
                {label}
            </dt>
            <dd className="mt-0.5 text-gray-900 dark:text-gray-200">
                {children}
            </dd>
        </div>
    );
}

/**
 * One message in the thread.
 *
 * Support on the left with an icon, the business on the right in brand
 * colour — the same convention as every messaging app, so nobody has to
 * read the attribution line to know who said what.
 */
function Bubble({
    fromSupport,
    at,
    children,
}: {
    fromSupport: boolean;
    at: string;
    children: string;
}) {
    const when = new Date(at).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        // No seconds. Nobody has ever needed to know a support reply
        // landed at :47 past.
        hour: '2-digit',
        minute: '2-digit',
    });

    return (
        <div
            className={`flex items-end gap-2 ${fromSupport ? '' : 'justify-end'}`}
        >
            {fromSupport && (
                <span className="mb-5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300">
                    <LifebuoyIcon className="h-4 w-4" />
                </span>
            )}

            <div className="max-w-[78%]">
                <div
                    className={`rounded-2xl px-4 py-2.5 ${
                        fromSupport
                            ? 'rounded-bl-sm bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100'
                            : 'rounded-br-sm bg-indigo-600 text-white'
                    }`}
                >
                    <p className="whitespace-pre-wrap break-words text-sm leading-relaxed">
                        {children}
                    </p>
                </div>

                <p
                    className={`mt-1 px-1 text-[11px] text-gray-400 ${fromSupport ? '' : 'text-end'}`}
                >
                    {fromSupport ? 'BiasharaMax Support' : 'You'} · {when}
                </p>
            </div>
        </div>
    );
}
