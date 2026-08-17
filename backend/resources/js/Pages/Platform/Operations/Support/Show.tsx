import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import Checkbox from '@/Components/Checkbox';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface MessageRow {
    id: string;
    author_type: string;
    author_id: string;
    body: string;
    is_internal_note: boolean;
    created_at: string;
}

interface TicketDetail {
    id: string;
    ticket_number: string;
    business: { id: string; name: string } | null;
    department: { id: string; name: string } | null;
    assigned_agent: { id: string; name: string | null } | null;
    category: string;
    priority: string;
    status: string;
    subject: string;
    description: string;
    satisfaction_rating: number | null;
    response_time_minutes: number | null;
    messages: MessageRow[];
}

export default function SupportTicketShow({
    ticket,
}: {
    ticket: TicketDetail;
}) {
    const { notify } = useBiNotification();
    const { data, setData, post, processing, reset } = useForm({
        body: '',
        is_internal_note: false,
    });

    const reply = (e: FormEvent) => {
        e.preventDefault();

        post(route('platform.operations.support.reply', ticket.id), {
            onSuccess: () => {
                reset();
                notify('Reply sent.', 'success');
            },
        });
    };

    const resolve = () => {
        router.post(
            route('platform.operations.support.resolve', ticket.id),
            {},
            { onSuccess: () => notify('Ticket resolved.', 'success') },
        );
    };

    const close = () => {
        router.post(
            route('platform.operations.support.close', ticket.id),
            {},
            { onSuccess: () => notify('Ticket closed.', 'success') },
        );
    };

    const reopen = () => {
        router.post(
            route('platform.operations.support.reopen', ticket.id),
            {},
            { onSuccess: () => notify('Ticket reopened.', 'success') },
        );
    };

    return (
        <PlatformLayout>
            <div className="space-y-6">
                <Link
                    href={route('platform.operations.support.index')}
                    className="text-sm text-indigo-600 hover:underline"
                >
                    ← Back to tickets
                </Link>

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {ticket.subject}
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {ticket.ticket_number} ·{' '}
                            {ticket.business?.name ?? 'Platform'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <BiBadge variant="info">{ticket.priority}</BiBadge>
                        <BiBadge variant="success">{ticket.status}</BiBadge>
                    </div>
                </div>

                <BiCard title="Description">
                    <p className="text-sm text-gray-700 dark:text-gray-300">
                        {ticket.description}
                    </p>
                </BiCard>

                <BiCard title="Conversation">
                    <div className="space-y-4">
                        {ticket.messages.map((message) => (
                            <div
                                key={message.id}
                                className={`rounded-md p-3 text-sm ${message.is_internal_note ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-gray-50 dark:bg-gray-800'}`}
                            >
                                <p className="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {message.author_type}{' '}
                                    {message.is_internal_note &&
                                        '· Internal note'}{' '}
                                    ·{' '}
                                    {new Date(
                                        message.created_at,
                                    ).toLocaleString()}
                                </p>
                                <p className="text-gray-900 dark:text-gray-100">
                                    {message.body}
                                </p>
                            </div>
                        ))}
                        {ticket.messages.length === 0 && (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                No replies yet.
                            </p>
                        )}
                    </div>

                    <form
                        onSubmit={reply}
                        className="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-700"
                    >
                        <textarea
                            className="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                            rows={3}
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Write a reply..."
                        />
                        <div className="flex items-center justify-between">
                            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <Checkbox
                                    checked={data.is_internal_note}
                                    onChange={(e) =>
                                        setData(
                                            'is_internal_note',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Internal note (not visible to business)
                            </label>
                            <BiButton type="submit" disabled={processing}>
                                Send
                            </BiButton>
                        </div>
                    </form>
                </BiCard>

                <div className="flex gap-3">
                    {ticket.status !== 'resolved' && (
                        <BiButton onClick={resolve} variant="secondary">
                            Mark resolved
                        </BiButton>
                    )}
                    {ticket.status !== 'closed' && (
                        <BiButton onClick={close} variant="secondary">
                            Close ticket
                        </BiButton>
                    )}
                    {(ticket.status === 'resolved' ||
                        ticket.status === 'closed') && (
                        <BiButton onClick={reopen} variant="secondary">
                            Reopen
                        </BiButton>
                    )}
                </div>
            </div>
        </PlatformLayout>
    );
}
