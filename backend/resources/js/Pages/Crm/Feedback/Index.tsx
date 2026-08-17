import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import StatCard from '@/Components/StatCard';
import TextInput from '@/Components/TextInput';
import CrmLayout from '@/Layouts/CrmLayout';
import {
    CustomerFeedback,
    FeedbackDashboardSummary,
    FeedbackStatus,
    FeedbackType,
} from '@/types/crm';
import {
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    StarIcon,
} from '@heroicons/react/24/outline';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const STATUS_VARIANT: Record<
    FeedbackStatus,
    'warning' | 'success' | 'neutral' | 'info'
> = {
    open: 'warning',
    pending: 'info',
    resolved: 'success',
    closed: 'neutral',
};

interface FeedbackFormData {
    customer_id: string;
    type: FeedbackType;
    rating: string;
    subject: string;
    body: string;
}

const emptyForm: FeedbackFormData = {
    customer_id: '',
    type: 'review',
    rating: '',
    subject: '',
    body: '',
};

export default function FeedbackIndex({
    feedback,
    summary,
    typeBreakdown,
    filters,
}: {
    feedback: {
        data: CustomerFeedback[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    summary: FeedbackDashboardSummary;
    typeBreakdown: Array<{ type: string; count: number }>;
    filters: { status?: string; type?: string };
}) {
    const [creating, setCreating] = useState(false);
    const createForm = useForm<FeedbackFormData>(emptyForm);

    const updateFilter = (key: 'status' | 'type', value: string) => {
        router.get(
            route('crm.feedback.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true },
        );
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('crm.feedback.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    return (
        <CrmLayout title="Customer Feedback">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={<ChatBubbleLeftRightIcon className="h-5 w-5" />}
                    iconClassName="bg-indigo-600"
                    title="Total Feedback"
                    value={summary.total_feedback}
                />
                <StatCard
                    icon={<ClockIcon className="h-5 w-5" />}
                    iconClassName="bg-amber-600"
                    title="Open"
                    value={summary.open_count}
                    deltaTone="warning"
                />
                <StatCard
                    icon={<CheckCircleIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="Resolved"
                    value={summary.resolved_count}
                />
                <StatCard
                    icon={<ExclamationTriangleIcon className="h-5 w-5" />}
                    iconClassName="bg-rose-600"
                    title="Complaints This Month"
                    value={summary.complaints_this_month}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <StatCard
                    icon={<StarIcon className="h-5 w-5" />}
                    iconClassName="bg-purple-600"
                    title="Average Rating"
                    value={summary.average_rating || '—'}
                />
                {typeBreakdown.length > 0 && (
                    <Card title="By Type">
                        <div className="flex flex-wrap gap-3">
                            {typeBreakdown.map((row) => (
                                <Badge key={row.type} variant="neutral">
                                    {row.type} · {row.count}
                                </Badge>
                            ))}
                        </div>
                    </Card>
                )}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex gap-3">
                    <SelectInput
                        value={filters.status ?? ''}
                        onChange={(e) => updateFilter('status', e.target.value)}
                    >
                        <option value="">All statuses</option>
                        <option value="open">Open</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </SelectInput>
                    <SelectInput
                        value={filters.type ?? ''}
                        onChange={(e) => updateFilter('type', e.target.value)}
                    >
                        <option value="">All types</option>
                        <option value="rating">Rating</option>
                        <option value="review">Review</option>
                        <option value="complaint">Complaint</option>
                    </SelectInput>
                </div>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Log Feedback
                </PrimaryButton>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Customer',
                                'Type',
                                'Subject',
                                'Status',
                                'Assigned To',
                                'Date',
                                '',
                            ].map((h) => (
                                <th
                                    key={h}
                                    className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {feedback.data.map((item) => (
                            <tr
                                key={item.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {item.customer?.name ?? 'Anonymous'}
                                </td>
                                <td className="px-4 py-3 text-sm capitalize text-gray-700 dark:text-gray-300">
                                    {item.type}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {item.subject ?? item.body.slice(0, 40)}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={STATUS_VARIANT[item.status]}
                                    >
                                        {item.status}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {item.assigned_to?.name ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {item.created_at}
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <Link
                                        href={route(
                                            'crm.feedback.show',
                                            item.id,
                                        )}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {feedback.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No feedback logged yet.
                    </p>
                )}

                {feedback.meta.links.length > 3 && (
                    <div className="flex flex-wrap gap-1 border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                        {feedback.meta.links.map((link, index) => (
                            <button
                                key={index}
                                disabled={!link.url}
                                onClick={() =>
                                    link.url &&
                                    router.get(
                                        link.url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                className={`rounded px-3 py-1 text-sm ${
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-gray-800'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            <Modal show={creating} onClose={() => setCreating(false)}>
                <form onSubmit={submitCreate} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Log customer feedback
                    </h2>
                    <div className="mt-4 space-y-4">
                        <SelectInput
                            className="block w-full"
                            value={createForm.data.type}
                            onChange={(e) =>
                                createForm.setData(
                                    'type',
                                    e.target.value as FeedbackType,
                                )
                            }
                        >
                            <option value="review">Review</option>
                            <option value="rating">Rating</option>
                            <option value="complaint">Complaint</option>
                        </SelectInput>
                        {createForm.data.type === 'rating' && (
                            <TextInput
                                type="number"
                                min={1}
                                max={5}
                                placeholder="Rating (1-5)"
                                className="block w-full"
                                value={createForm.data.rating}
                                onChange={(e) =>
                                    createForm.setData('rating', e.target.value)
                                }
                            />
                        )}
                        <TextInput
                            placeholder="Subject (optional)"
                            className="block w-full"
                            value={createForm.data.subject}
                            onChange={(e) =>
                                createForm.setData('subject', e.target.value)
                            }
                        />
                        <div>
                            <textarea
                                placeholder="Feedback details"
                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                rows={4}
                                value={createForm.data.body}
                                onChange={(e) =>
                                    createForm.setData('body', e.target.value)
                                }
                            />
                            <InputError
                                message={createForm.errors.body}
                                className="mt-2"
                            />
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreating(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={createForm.processing}
                        >
                            Log Feedback
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </CrmLayout>
    );
}
