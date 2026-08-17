import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import InputError from '@/Components/InputError';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface TicketRow {
    id: string;
    ticket_number: string;
    subject: string;
    category: string;
    priority: string;
    status: string;
    messages_count: number;
    created_at: string;
}

/**
 * Resolved is green but closed is grey, deliberately: one means "we
 * believe this is fixed" and the other means "this conversation is
 * over". A customer who still has the problem needs to be able to tell
 * those apart at a glance.
 */
const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral'
> = {
    open: 'warning',
    pending: 'warning',
    in_progress: 'warning',
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

export default function SupportIndex({
    tickets,
    categories,
    priorities,
}: {
    tickets: {
        data: TicketRow[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            links: Array<{
                url: string | null;
                label: string;
                active: boolean;
            }>;
        };
    };
    categories: Record<string, string>;
    priorities: Record<string, string>;
}) {
    const [creating, setCreating] = useState(false);

    const form = useForm({
        subject: '',
        description: '',
        category: 'technical',
        priority: 'medium',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        form.post(route('support.store'), {
            onSuccess: () => {
                setCreating(false);
                form.reset();
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Support" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                Support
                            </h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Get help from the BiasharaMax team.
                            </p>
                        </div>
                        <BiButton onClick={() => setCreating(true)}>
                            New ticket
                        </BiButton>
                    </div>

                    <BiCard title="Your tickets">
                        {tickets.data.length === 0 ? (
                            <div className="py-12 text-center">
                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    No tickets yet
                                </p>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Stuck on something? Open a ticket and
                                    we&apos;ll get back to you.
                                </p>
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {tickets.data.map((ticket) => (
                                    <Link
                                        key={ticket.id}
                                        href={route('support.show', ticket.id)}
                                        className="flex items-center justify-between gap-4 py-3 transition hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {ticket.subject}
                                            </p>
                                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                {ticket.ticket_number} ·{' '}
                                                {categories[ticket.category] ??
                                                    ticket.category}{' '}
                                                · {ticket.messages_count}{' '}
                                                {ticket.messages_count === 1
                                                    ? 'reply'
                                                    : 'replies'}
                                            </p>
                                        </div>

                                        <BiBadge
                                            variant={
                                                STATUS_VARIANT[ticket.status] ??
                                                'neutral'
                                            }
                                        >
                                            {STATUS_LABEL[ticket.status] ??
                                                ticket.status}
                                        </BiBadge>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </BiCard>

                    {/*
                      Only worth showing when there is more than one page.
                      A lone "1" under a short list is noise that implies
                      there might be more.
                    */}
                    {tickets.meta.last_page > 1 && (
                        <nav
                            aria-label="Ticket pages"
                            className="flex flex-wrap items-center justify-center gap-1"
                        >
                            {tickets.meta.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url ?? '#'}
                                    // A disabled Previous/Next still
                                    // renders, to keep the row from
                                    // shifting sideways as you page.
                                    className={`rounded-md px-3 py-1.5 text-sm transition ${
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : link.url
                                              ? 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
                                              : 'cursor-not-allowed text-gray-300 dark:text-gray-600'
                                    }`}
                                    dangerouslySetInnerHTML={{
                                        // Laravel puts `&laquo;` / `&raquo;`
                                        // entities in these labels.
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </nav>
                    )}
                </div>
            </div>

            <BiModal
                show={creating}
                onClose={() => setCreating(false)}
                title="New support ticket"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreating(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="new-ticket-form"
                            disabled={form.processing}
                        >
                            Send to support
                        </BiButton>
                    </>
                }
            >
                <form
                    id="new-ticket-form"
                    onSubmit={submit}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Subject
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={form.data.subject}
                            onChange={(e) =>
                                form.setData('subject', e.target.value)
                            }
                            placeholder="Short summary of the problem"
                        />
                        <InputError
                            message={form.errors.subject}
                            className="mt-1"
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Category
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={form.data.category}
                                onChange={(e) =>
                                    form.setData('category', e.target.value)
                                }
                            >
                                {Object.entries(categories).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </SelectInput>
                            <InputError
                                message={form.errors.category}
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Priority
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={form.data.priority}
                                onChange={(e) =>
                                    form.setData('priority', e.target.value)
                                }
                            >
                                {Object.entries(priorities).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </SelectInput>
                            <InputError
                                message={form.errors.priority}
                                className="mt-1"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            What happened?
                        </label>
                        <textarea
                            rows={6}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            placeholder="What you were doing, what you expected, and what happened instead. Include a product name, invoice number or screenshot reference if you have one."
                        />
                        <InputError
                            message={form.errors.description}
                            className="mt-1"
                        />
                    </div>
                </form>
            </BiModal>
        </AuthenticatedLayout>
    );
}
