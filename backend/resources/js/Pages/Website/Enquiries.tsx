import Badge from '@/Components/Badge';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import WebsiteLayout from '@/Layouts/WebsiteLayout';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type EnquiryStatus = 'new' | 'responded' | 'closed';

interface ProductEnquiry {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    message: string;
    status: EnquiryStatus;
    reply: string | null;
    responded_at: string | null;
    created_at: string;
    product: { id: string; name: string } | null;
}

const STATUS_VARIANT: Record<EnquiryStatus, 'warning' | 'success' | 'neutral'> =
    {
        new: 'warning',
        responded: 'success',
        closed: 'neutral',
    };

export default function WebsiteEnquiries({
    enquiries,
    filters,
}: {
    enquiries: {
        data: ProductEnquiry[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    filters: { status?: string };
}) {
    const [replying, setReplying] = useState<ProductEnquiry | null>(null);
    const replyForm = useForm({ reply: '' });

    const applyFilter = (status: string) => {
        router.get(
            route('website.enquiries.index'),
            { status: status || undefined },
            { preserveState: true },
        );
    };

    const updateStatus = (enquiry: ProductEnquiry, status: string) => {
        router.patch(
            route('website.enquiries.status.update', enquiry.id),
            { status },
            { preserveScroll: true },
        );
    };

    const submitReply = (e: FormEvent) => {
        e.preventDefault();
        if (!replying) return;
        replyForm.post(route('website.enquiries.reply', replying.id), {
            onSuccess: () => {
                setReplying(null);
                replyForm.reset();
            },
        });
    };

    return (
        <WebsiteLayout title="Product Enquiries">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Product Enquiries
                </h3>
                <SelectInput
                    value={filters.status ?? ''}
                    onChange={(e) => applyFilter(e.target.value)}
                >
                    <option value="">All statuses</option>
                    <option value="new">New</option>
                    <option value="responded">Responded</option>
                    <option value="closed">Closed</option>
                </SelectInput>
            </div>

            <div className="space-y-4">
                {enquiries.data.map((enquiry) => (
                    <div
                        key={enquiry.id}
                        className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="font-medium text-gray-900 dark:text-gray-100">
                                    {enquiry.name}{' '}
                                    {enquiry.product && (
                                        <span className="text-gray-500 dark:text-gray-400">
                                            about {enquiry.product.name}
                                        </span>
                                    )}
                                </p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    {[enquiry.email, enquiry.phone]
                                        .filter(Boolean)
                                        .join(' · ')}{' '}
                                    · {enquiry.created_at}
                                </p>
                            </div>
                            <Badge variant={STATUS_VARIANT[enquiry.status]}>
                                {enquiry.status}
                            </Badge>
                        </div>

                        <p className="mt-3 text-sm text-gray-700 dark:text-gray-300">
                            {enquiry.message}
                        </p>

                        {enquiry.reply && (
                            <div className="mt-3 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900/40">
                                <p className="font-medium text-gray-900 dark:text-gray-100">
                                    Your reply:
                                </p>
                                <p className="mt-1 text-gray-700 dark:text-gray-300">
                                    {enquiry.reply}
                                </p>
                            </div>
                        )}

                        <div className="mt-4 flex items-center gap-3">
                            {!enquiry.reply && (
                                <PrimaryButton
                                    onClick={() => setReplying(enquiry)}
                                >
                                    Reply
                                </PrimaryButton>
                            )}
                            {enquiry.status !== 'closed' && (
                                <button
                                    onClick={() =>
                                        updateStatus(enquiry, 'closed')
                                    }
                                    className="text-sm text-gray-500 hover:underline dark:text-gray-400"
                                >
                                    Mark as closed
                                </button>
                            )}
                        </div>

                        {replying?.id === enquiry.id && (
                            <form
                                onSubmit={submitReply}
                                className="mt-4 space-y-2"
                            >
                                <textarea
                                    placeholder="Write a reply..."
                                    rows={3}
                                    value={replyForm.data.reply}
                                    onChange={(e) =>
                                        replyForm.setData(
                                            'reply',
                                            e.target.value,
                                        )
                                    }
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                />
                                <InputError message={replyForm.errors.reply} />
                                <div className="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setReplying(null)}
                                        className="rounded-md px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300"
                                    >
                                        Cancel
                                    </button>
                                    <PrimaryButton
                                        type="submit"
                                        disabled={replyForm.processing}
                                    >
                                        Send Reply
                                    </PrimaryButton>
                                </div>
                            </form>
                        )}
                    </div>
                ))}

                {enquiries.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No enquiries yet.
                    </p>
                )}
            </div>
        </WebsiteLayout>
    );
}
