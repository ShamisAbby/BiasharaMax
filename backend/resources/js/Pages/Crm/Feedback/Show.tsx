import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import CrmLayout from '@/Layouts/CrmLayout';
import { CustomerFeedback, FeedbackStatus } from '@/types/crm';
import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

const STATUS_VARIANT: Record<
    FeedbackStatus,
    'warning' | 'success' | 'neutral' | 'info'
> = {
    open: 'warning',
    pending: 'info',
    resolved: 'success',
    closed: 'neutral',
};

export default function FeedbackShow({
    feedback,
    agents,
}: {
    feedback: CustomerFeedback;
    agents: { id: string; name: string }[];
}) {
    const replyForm = useForm({ body: '' });

    const submitReply = (e: FormEvent) => {
        e.preventDefault();
        replyForm.post(route('crm.feedback.replies.store', feedback.id), {
            preserveScroll: true,
            onSuccess: () => replyForm.reset(),
        });
    };

    const updateStatus = (status: string) => {
        router.patch(
            route('crm.feedback.status.update', feedback.id),
            { status },
            { preserveScroll: true },
        );
    };

    const assign = (userId: string) => {
        router.patch(
            route('crm.feedback.assign', feedback.id),
            { assigned_to: userId || null },
            { preserveScroll: true },
        );
    };

    return (
        <CrmLayout title={feedback.subject ?? 'Feedback'}>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {feedback.subject ?? `${feedback.type} feedback`}
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {feedback.customer?.name ?? 'Anonymous customer'} ·{' '}
                        {feedback.created_at}
                    </p>
                </div>
                <Badge variant={STATUS_VARIANT[feedback.status]}>
                    {feedback.status}
                </Badge>
            </div>

            <div className="grid gap-6 sm:grid-cols-3">
                <Card title="Type">
                    <p className="font-medium capitalize text-gray-900 dark:text-gray-100">
                        {feedback.type}
                    </p>
                    {feedback.rating && (
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {feedback.rating} / 5
                        </p>
                    )}
                </Card>
                <Card title="Status">
                    <SelectInput
                        className="block w-full"
                        value={feedback.status}
                        onChange={(e) => updateStatus(e.target.value)}
                    >
                        <option value="open">Open</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </SelectInput>
                </Card>
                <Card title="Assigned To">
                    <SelectInput
                        className="block w-full"
                        value={feedback.assigned_to?.id ?? ''}
                        onChange={(e) => assign(e.target.value)}
                    >
                        <option value="">Unassigned</option>
                        {agents.map((agent) => (
                            <option key={agent.id} value={agent.id}>
                                {agent.name}
                            </option>
                        ))}
                    </SelectInput>
                </Card>
            </div>

            <Card title="Feedback">
                <p className="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">
                    {feedback.body}
                </p>
            </Card>

            <Card title="Replies">
                <div className="space-y-4">
                    {feedback.replies.map((reply) => (
                        <div
                            key={reply.id}
                            className="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900/40"
                        >
                            <p className="text-gray-900 dark:text-gray-100">
                                {reply.body}
                            </p>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {reply.author?.name ?? 'Staff'} ·{' '}
                                {reply.created_at}
                            </p>
                        </div>
                    ))}
                    {feedback.replies.length === 0 && (
                        <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No replies yet.
                        </p>
                    )}
                </div>

                <form onSubmit={submitReply} className="mt-6 space-y-3">
                    <textarea
                        placeholder="Write a reply..."
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        rows={3}
                        value={replyForm.data.body}
                        onChange={(e) =>
                            replyForm.setData('body', e.target.value)
                        }
                    />
                    <InputError message={replyForm.errors.body} />
                    <div className="flex justify-end">
                        <PrimaryButton
                            type="submit"
                            disabled={replyForm.processing}
                        >
                            Send Reply
                        </PrimaryButton>
                    </div>
                </form>
            </Card>
        </CrmLayout>
    );
}
