import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformSubscriptionsLayout from '@/Layouts/PlatformSubscriptionsLayout';
import { formatCurrency } from '@/lib/currency';
import { PageProps } from '@/types';
import {
    EnvelopeIcon,
    PaperAirplaneIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';
import { router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

interface Subscriber {
    id: string;
    status:
        | 'trialing'
        | 'active'
        | 'past_due'
        | 'canceled'
        | 'expired'
        | 'suspended';
    billing_cycle: string | null;
    trial_ends_at: string | null;
    current_period_end: string | null;
    grace_period_ends_at: string | null;
    is_in_grace_period: boolean;
    is_custom_pricing: boolean;
    effective_price: number;
    auto_renew: boolean;
    business: {
        id: string;
        name: string;
        owner_name: string | null;
        owner_email: string | null;
    } | null;
    plan: { id: string; name: string } | null;
    created_at: string;
}

interface PaginatedSubscribers {
    data: Subscriber[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const STATUS_VARIANT = {
    trialing: 'info',
    active: 'success',
    past_due: 'warning',
    canceled: 'neutral',
    expired: 'danger',
    suspended: 'danger',
} as const;

type ComposeTarget = null | Subscriber;

export default function SubscribersIndex({
    subscribers,
    filters,
    plans,
    ownerEmailCount,
}: {
    subscribers: PaginatedSubscribers;
    filters: Record<string, string>;
    plans: Array<{ id: string; name: string }>;
    ownerEmailCount: number;
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const page = usePage<PageProps<{ flash: { broadcast_count?: number } }>>();

    const [search, setSearch] = useState(filters.search ?? '');
    const [assigning, setAssigning] = useState<Subscriber | null>(null);
    const [extending, setExtending] = useState<Subscriber | null>(null);
    const [renewing, setRenewing] = useState<Subscriber | null>(null);
    const [composing, setComposing] = useState<ComposeTarget | 'closed'>(
        'closed',
    );

    useEffect(() => {
        const count = page.props.flash?.broadcast_count;
        if (count !== undefined) {
            notify(
                `Email queued for ${count} subscriber${count !== 1 ? 's' : ''}.`,
                'success',
            );
        }
    }, [page.props.flash]);

    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('platform.subscriptions.subscribers.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const onSearchSubmit = (e: FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const assignForm = useForm({
        subscription_plan_id: '',
        billing_cycle: 'monthly',
        custom_price: '',
    });

    const extendForm = useForm({ days: '30' });

    const renewForm = useForm({
        amount: '',
        payment_method: 'cash',
        notes: '',
    });

    const openAssign = (subscriber: Subscriber) => {
        assignForm.setData({
            subscription_plan_id: subscriber.plan?.id ?? '',
            billing_cycle: subscriber.billing_cycle ?? 'monthly',
            custom_price: subscriber.is_custom_pricing
                ? String(subscriber.effective_price)
                : '',
        });
        setAssigning(subscriber);
    };

    const submitAssign = (e: FormEvent) => {
        e.preventDefault();
        if (!assigning) return;

        assignForm.patch(
            route(
                'platform.subscriptions.subscribers.assign-plan',
                assigning.id,
            ),
            {
                onSuccess: () => {
                    setAssigning(null);
                    notify('Plan assigned.', 'success');
                },
            },
        );
    };

    const submitExtend = (e: FormEvent) => {
        e.preventDefault();
        if (!extending) return;

        extendForm.post(
            route(
                'platform.subscriptions.subscribers.extend-trial',
                extending.id,
            ),
            {
                onSuccess: () => {
                    setExtending(null);
                    notify('Trial extended.', 'success');
                },
            },
        );
    };

    const submitRenew = (e: FormEvent) => {
        e.preventDefault();
        if (!renewing) return;

        renewForm.post(
            route('platform.subscriptions.subscribers.renew', renewing.id),
            {
                onSuccess: () => {
                    setRenewing(null);
                    notify('Subscription renewed.', 'success');
                },
            },
        );
    };

    const suspend = (subscriber: Subscriber) => {
        askConfirm({
            title: `Suspend this subscription for ${subscriber.business?.name}?`,
            tone: 'danger',
            confirmLabel: 'Suspend',
            onConfirm: () => {
                router.post(
                    route(
                        'platform.subscriptions.subscribers.suspend',
                        subscriber.id,
                    ),
                );
            },
        });
    };

    const restore = (subscriber: Subscriber) => {
        router.post(
            route('platform.subscriptions.subscribers.restore', subscriber.id),
        );
    };

    const cancel = (subscriber: Subscriber) => {
        askConfirm({
            title: `Cancel this subscription for ${subscriber.business?.name}?`,
            tone: 'danger',
            confirmLabel: 'Cancel',
            onConfirm: () => {
                router.post(
                    route(
                        'platform.subscriptions.subscribers.cancel',
                        subscriber.id,
                    ),
                );
            },
        });
    };

    const columns: BiTableColumn<Subscriber>[] = [
        {
            key: 'business',
            label: 'Business',
            render: (s) => (
                <>
                    <p className="font-medium text-gray-900 dark:text-gray-100">
                        {s.business?.name ?? '—'}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {s.business?.owner_email}
                    </p>
                </>
            ),
        },
        {
            key: 'plan',
            label: 'Plan',
            render: (s) => (
                <>
                    <p className="text-gray-900 dark:text-gray-100">
                        {s.plan?.name ?? '—'}
                    </p>
                    {s.is_custom_pricing && (
                        <BiBadge variant="info">Custom price</BiBadge>
                    )}
                </>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (s) => (
                <>
                    <BiBadge variant={STATUS_VARIANT[s.status]}>
                        {s.status}
                    </BiBadge>
                    {s.is_in_grace_period && (
                        <p className="mt-1 text-xs text-amber-600">
                            In grace period
                        </p>
                    )}
                </>
            ),
        },
        {
            key: 'period',
            label: 'Trial / period end',
            render: (s) => {
                const date =
                    s.status === 'trialing'
                        ? s.trial_ends_at
                        : s.current_period_end;
                return date ? new Date(date).toLocaleDateString() : '—';
            },
        },
        {
            key: 'actions',
            label: 'Actions',
            align: 'right',
            render: (s) => (
                <div className="flex flex-wrap justify-end gap-3">
                    <button
                        onClick={() => setComposing(s)}
                        className="flex items-center gap-1 text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        <EnvelopeIcon className="h-4 w-4" />
                        Email
                    </button>
                    <button
                        onClick={() => openAssign(s)}
                        className="text-indigo-600 hover:underline"
                    >
                        Assign plan
                    </button>
                    {s.status === 'trialing' && (
                        <button
                            onClick={() => setExtending(s)}
                            className="text-indigo-600 hover:underline"
                        >
                            Extend trial
                        </button>
                    )}
                    <button
                        onClick={() => setRenewing(s)}
                        className="text-emerald-600 hover:underline"
                    >
                        Renew
                    </button>
                    {['suspended', 'canceled'].includes(s.status) ? (
                        <button
                            onClick={() => restore(s)}
                            className="text-emerald-600 hover:underline"
                        >
                            Restore
                        </button>
                    ) : (
                        <button
                            onClick={() => suspend(s)}
                            className="text-amber-600 hover:underline"
                        >
                            Suspend
                        </button>
                    )}
                    {s.status !== 'canceled' && (
                        <button
                            onClick={() => cancel(s)}
                            className="text-red-600 hover:underline"
                        >
                            Cancel
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <PlatformSubscriptionsLayout title="Subscribers">
            <BiDataGrid
                title="Subscribers"
                description={`${subscribers.meta.total} total`}
                columns={columns}
                paginated={subscribers}
                rowKey={(s) => s.id}
                emptyMessage="No subscribers match these filters."
                toolbar={
                    <>
                        <form onSubmit={onSearchSubmit} className="flex gap-2">
                            <TextInput
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by business name"
                                className="w-72"
                            />
                            <SecondaryButton type="submit">
                                Search
                            </SecondaryButton>
                        </form>

                        <SelectInput
                            value={filters.status ?? ''}
                            onChange={(e) =>
                                applyFilters({ status: e.target.value })
                            }
                        >
                            <option value="">All statuses</option>
                            <option value="trialing">Trialing</option>
                            <option value="active">Active</option>
                            <option value="past_due">Past due</option>
                            <option value="suspended">Suspended</option>
                            <option value="canceled">Canceled</option>
                            <option value="expired">Expired</option>
                        </SelectInput>

                        <BiButton
                            onClick={() => setComposing(null)}
                            className="flex shrink-0 items-center gap-2"
                        >
                            <UsersIcon className="h-4 w-4" />
                            Send Email to All
                        </BiButton>
                    </>
                }
            />

            <BiModal
                show={assigning !== null}
                onClose={() => setAssigning(null)}
                title={`Assign plan — ${assigning?.business?.name ?? ''}`}
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setAssigning(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="assign-plan-form"
                            disabled={assignForm.processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="assign-plan-form"
                    onSubmit={submitAssign}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Plan
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={assignForm.data.subscription_plan_id}
                            onChange={(e) =>
                                assignForm.setData(
                                    'subscription_plan_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">Select a plan</option>
                            {plans.map((plan) => (
                                <option key={plan.id} value={plan.id}>
                                    {plan.name}
                                </option>
                            ))}
                        </SelectInput>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Billing cycle
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={assignForm.data.billing_cycle}
                            onChange={(e) =>
                                assignForm.setData(
                                    'billing_cycle',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                            <option value="lifetime">Lifetime</option>
                        </SelectInput>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Custom price (optional — negotiated deal)
                        </label>
                        <TextInput
                            type="number"
                            className="mt-1 block w-full"
                            placeholder="Leave blank to use catalog price"
                            value={assignForm.data.custom_price}
                            onChange={(e) =>
                                assignForm.setData(
                                    'custom_price',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                </form>
            </BiModal>

            <BiModal
                show={extending !== null}
                onClose={() => setExtending(null)}
                title={`Extend trial — ${extending?.business?.name ?? ''}`}
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setExtending(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="extend-trial-form"
                            disabled={extendForm.processing}
                        >
                            Extend
                        </BiButton>
                    </>
                }
            >
                <form id="extend-trial-form" onSubmit={submitExtend}>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Additional days
                    </label>
                    <TextInput
                        type="number"
                        className="mt-1 block w-full"
                        value={extendForm.data.days}
                        onChange={(e) =>
                            extendForm.setData('days', e.target.value)
                        }
                    />
                </form>
            </BiModal>

            <BiModal
                show={renewing !== null}
                onClose={() => setRenewing(null)}
                title={`Record payment & renew — ${renewing?.business?.name ?? ''}`}
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setRenewing(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="renew-form"
                            disabled={renewForm.processing}
                        >
                            Confirm renewal
                        </BiButton>
                    </>
                }
            >
                <form
                    id="renew-form"
                    onSubmit={submitRenew}
                    className="space-y-4"
                >
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        BiasharaMax has no payment gateway yet — record what was
                        actually paid (cash, bank transfer, mobile money) to
                        renew this subscription's billing period.
                    </p>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Amount paid
                        </label>
                        <TextInput
                            type="number"
                            className="mt-1 block w-full"
                            value={renewForm.data.amount}
                            onChange={(e) =>
                                renewForm.setData('amount', e.target.value)
                            }
                        />
                        {renewing && (
                            <p className="mt-1 text-xs text-gray-500">
                                Plan price:{' '}
                                {formatCurrency(renewing.effective_price)}
                            </p>
                        )}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Payment method
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={renewForm.data.payment_method}
                            onChange={(e) =>
                                renewForm.setData(
                                    'payment_method',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="mobile_money">Mobile money</option>
                        </SelectInput>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Notes (optional)
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={renewForm.data.notes}
                            onChange={(e) =>
                                renewForm.setData('notes', e.target.value)
                            }
                        />
                    </div>
                </form>
            </BiModal>
            {composing !== 'closed' && (
                <SubscriberComposeModal
                    target={composing}
                    ownerEmailCount={ownerEmailCount}
                    onClose={() => setComposing('closed')}
                    onSent={(msg) => {
                        notify(msg, 'success');
                        setComposing('closed');
                    }}
                />
            )}
        </PlatformSubscriptionsLayout>
    );
}

// ---------------------------------------------------------------------------
// Compose modal
// ---------------------------------------------------------------------------

function SubscriberComposeModal({
    target,
    ownerEmailCount,
    onClose,
    onSent,
}: {
    target: ComposeTarget;
    ownerEmailCount: number;
    onClose: () => void;
    onSent: (msg: string) => void;
}) {
    const isBroadcast = target === null;
    const form = useForm({ subject: '', body: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const routeName = isBroadcast
            ? route('platform.subscriptions.subscribers.broadcast')
            : route(
                  'platform.subscriptions.subscribers.send-email',
                  target!.id,
              );

        form.post(routeName, {
            onSuccess: () => {
                form.reset();
                onSent(
                    isBroadcast
                        ? `Email queued for ${ownerEmailCount} subscriber owner${ownerEmailCount !== 1 ? 's' : ''}.`
                        : `Email sent to ${target!.business?.owner_name ?? target!.business?.owner_email ?? 'subscriber'}.`,
                );
            },
        });
    };

    const recipientName =
        target?.business?.owner_name ?? target?.business?.owner_email ?? '—';
    const recipientEmail = target?.business?.owner_email ?? '';

    return (
        <BiModal
            show
            onClose={onClose}
            maxWidth="lg"
            title={
                isBroadcast
                    ? 'Send Email to All Subscriber Owners'
                    : `Send Email to ${recipientName}`
            }
            footer={
                <div className="flex items-center justify-end gap-3">
                    <SecondaryButton type="button" onClick={onClose}>
                        Cancel
                    </SecondaryButton>
                    <BiButton
                        type="submit"
                        form="subscriber-compose-form"
                        disabled={form.processing}
                        className="flex items-center gap-2"
                    >
                        <PaperAirplaneIcon className="h-4 w-4" />
                        {form.processing ? 'Sending…' : 'Send'}
                    </BiButton>
                </div>
            }
        >
            <div
                className={`mb-5 flex items-center gap-3 rounded-lg px-4 py-3 text-sm ${
                    isBroadcast
                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                        : 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
                }`}
            >
                {isBroadcast ? (
                    <>
                        <UsersIcon className="h-5 w-5 shrink-0" />
                        <span>
                            This email will be sent to{' '}
                            <strong>
                                {ownerEmailCount} business owner
                                {ownerEmailCount !== 1 ? 's' : ''}
                            </strong>{' '}
                            across all subscriptions.
                        </span>
                    </>
                ) : (
                    <>
                        <EnvelopeIcon className="h-5 w-5 shrink-0" />
                        <span>
                            To: <strong>{recipientName}</strong>{' '}
                            {recipientEmail && (
                                <span className="text-gray-500 dark:text-gray-400">
                                    ({recipientEmail})
                                </span>
                            )}
                        </span>
                    </>
                )}
            </div>

            <form
                id="subscriber-compose-form"
                onSubmit={submit}
                className="space-y-4"
            >
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Subject <span className="text-red-500">*</span>
                    </label>
                    <TextInput
                        className="mt-1 block w-full"
                        placeholder="e.g. Important update about your subscription"
                        value={form.data.subject}
                        onChange={(e) =>
                            form.setData('subject', e.target.value)
                        }
                        required
                    />
                    {form.errors.subject && (
                        <p className="mt-1 text-xs text-red-600">
                            {form.errors.subject}
                        </p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Message <span className="text-red-500">*</span>
                    </label>
                    <textarea
                        className="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        rows={7}
                        placeholder="Write your message here…"
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        required
                    />
                    {form.errors.body && (
                        <p className="mt-1 text-xs text-red-600">
                            {form.errors.body}
                        </p>
                    )}
                </div>
            </form>
        </BiModal>
    );
}
