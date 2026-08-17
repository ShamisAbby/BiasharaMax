import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import {
    CustomerCrmProfile,
    CustomerLoyaltyTransaction,
    CustomerNote,
    LoyaltyTransactionType,
} from '@/types/crm';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface TagOption {
    id: string;
    name: string;
    color: string | null;
}
interface GroupOption {
    id: string;
    name: string;
    is_vip: boolean;
}
interface RewardOption {
    id: string;
    name: string;
    points_cost: number;
    stock_quantity: number | null;
}

export default function CustomerShow({
    customer,
    notes,
    loyaltyTransactions,
    tags,
    groups,
    availableRewards,
}: {
    customer: CustomerCrmProfile;
    notes: CustomerNote[];
    loyaltyTransactions: CustomerLoyaltyTransaction[];
    tags: TagOption[];
    groups: GroupOption[];
    availableRewards: RewardOption[];
}) {
    const askConfirm = useConfirm();
    const noteForm = useForm({ body: '' });
    const loyaltyForm = useForm<{
        type: LoyaltyTransactionType;
        points: string;
        notes: string;
    }>({
        type: 'earn',
        points: '',
        notes: '',
    });
    const redeemForm = useForm<{ loyalty_reward_id: string }>({
        loyalty_reward_id: '',
    });
    const [selectedTagIds, setSelectedTagIds] = useState<string[]>(
        customer.tags.map((t) => t.id),
    );

    const submitNote = (e: FormEvent) => {
        e.preventDefault();
        noteForm.post(route('crm.customers.notes.store', customer.id), {
            onSuccess: () => noteForm.reset(),
        });
    };

    const deleteNote = (note: CustomerNote) => {
        askConfirm({
            title: 'Delete this note?',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('crm.customers.notes.destroy', [
                        customer.id,
                        note.id,
                    ]),
                );
            },
        });
    };

    const toggleTag = (tagId: string) => {
        const next = selectedTagIds.includes(tagId)
            ? selectedTagIds.filter((id) => id !== tagId)
            : [...selectedTagIds, tagId];
        setSelectedTagIds(next);
        router.patch(
            route('crm.customers.tags.update', customer.id),
            { tag_ids: next },
            { preserveScroll: true },
        );
    };

    const changeGroup = (groupId: string) => {
        router.patch(
            route('crm.customers.group.update', customer.id),
            { customer_group_id: groupId || null },
            { preserveScroll: true },
        );
    };

    const submitLoyalty = (e: FormEvent) => {
        e.preventDefault();
        loyaltyForm.post(route('crm.customers.loyalty.adjust', customer.id), {
            preserveScroll: true,
            onSuccess: () => loyaltyForm.reset(),
        });
    };

    const redeemReward = (rewardId: string) => {
        askConfirm({
            title: 'Redeem this reward for the customer?',
            tone: 'warning',
            confirmLabel: 'Redeem',
            onConfirm: () => {
                redeemForm.transform(() => ({ loyalty_reward_id: rewardId }));
                redeemForm.post(
                    route('crm.customers.loyalty.redeem', customer.id),
                    {
                        preserveScroll: true,
                    },
                );
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {customer.name}
                    </h2>
                    <Link
                        href={route('crm.customers.card', customer.id)}
                        className="text-sm font-medium text-indigo-600 hover:underline"
                    >
                        View Digital Loyalty Card
                    </Link>
                </div>
            }
        >
            <Head title={`CRM — ${customer.name}`} />

            <div className="py-8">
                <div className="mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <Card title="Lifetime Value">
                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {formatCurrency(customer.lifetime_value)}
                            </p>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {customer.sales_count} completed sale(s)
                            </p>
                        </Card>
                        <Card title="Balance">
                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {formatCurrency(customer.current_balance)}
                            </p>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {customer.customer_type === 'credit'
                                    ? `Limit ${formatCurrency(customer.credit_limit)}`
                                    : 'Cash customer'}
                            </p>
                        </Card>
                        <Card title="Loyalty Points">
                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {customer.loyalty_points}
                            </p>
                            {customer.loyalty_tier && (
                                <span className="mt-1 inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                    {customer.loyalty_tier.name} tier
                                </span>
                            )}
                        </Card>
                        <Card title="Segment">
                            <SelectInput
                                className="block w-full"
                                value={customer.group?.id ?? ''}
                                onChange={(e) => changeGroup(e.target.value)}
                            >
                                <option value="">No group</option>
                                {groups.map((group) => (
                                    <option key={group.id} value={group.id}>
                                        {group.name}
                                        {group.is_vip ? ' (VIP)' : ''}
                                    </option>
                                ))}
                            </SelectInput>
                        </Card>
                    </div>

                    <Card title="Tags">
                        <div className="flex flex-wrap gap-2">
                            {tags.map((tag) => {
                                const active = selectedTagIds.includes(tag.id);
                                return (
                                    <button
                                        key={tag.id}
                                        onClick={() => toggleTag(tag.id)}
                                        className="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium transition"
                                        style={{
                                            backgroundColor: active
                                                ? (tag.color ?? '#6366F1')
                                                : 'transparent',
                                            color: active
                                                ? '#fff'
                                                : (tag.color ?? '#6366F1'),
                                            border: `1px solid ${tag.color ?? '#6366F1'}`,
                                        }}
                                    >
                                        {tag.name}
                                    </button>
                                );
                            })}
                            {tags.length === 0 && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    No tags created yet.
                                </p>
                            )}
                        </div>
                    </Card>

                    <Card
                        title="Redeem Reward"
                        description="Spend the customer's points on a catalog reward"
                    >
                        {availableRewards.length > 0 ? (
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {availableRewards.map((reward) => {
                                    const outOfStock =
                                        reward.stock_quantity !== null &&
                                        reward.stock_quantity <= 0;
                                    const canAfford =
                                        customer.loyalty_points >=
                                        reward.points_cost;
                                    return (
                                        <div
                                            key={reward.id}
                                            className="flex items-center justify-between py-2.5 text-sm"
                                        >
                                            <div>
                                                <span className="text-gray-900 dark:text-gray-100">
                                                    {reward.name}
                                                </span>
                                                <span className="ml-2 text-gray-500 dark:text-gray-400">
                                                    {reward.points_cost} pts
                                                </span>
                                            </div>
                                            <SecondaryButton
                                                type="button"
                                                disabled={
                                                    outOfStock ||
                                                    !canAfford ||
                                                    redeemForm.processing
                                                }
                                                onClick={() =>
                                                    redeemReward(reward.id)
                                                }
                                            >
                                                {outOfStock
                                                    ? 'Out of stock'
                                                    : 'Redeem'}
                                            </SecondaryButton>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                No active rewards in the catalog yet.
                            </p>
                        )}
                        <InputError
                            message={redeemForm.errors.loyalty_reward_id}
                            className="mt-2"
                        />
                    </Card>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card title="Adjust Loyalty Points">
                            <form
                                onSubmit={submitLoyalty}
                                className="space-y-4"
                            >
                                <SelectInput
                                    className="block w-full"
                                    value={loyaltyForm.data.type}
                                    onChange={(e) =>
                                        loyaltyForm.setData(
                                            'type',
                                            e.target
                                                .value as LoyaltyTransactionType,
                                        )
                                    }
                                >
                                    <option value="earn">Earn</option>
                                    <option value="redeem">Redeem</option>
                                    <option value="adjustment">
                                        Adjustment (+/-)
                                    </option>
                                </SelectInput>
                                <div>
                                    <input
                                        type="number"
                                        placeholder="Points"
                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={loyaltyForm.data.points}
                                        onChange={(e) =>
                                            loyaltyForm.setData(
                                                'points',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={loyaltyForm.errors.points}
                                        className="mt-2"
                                    />
                                </div>
                                <input
                                    placeholder="Reason (optional)"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={loyaltyForm.data.notes}
                                    onChange={(e) =>
                                        loyaltyForm.setData(
                                            'notes',
                                            e.target.value,
                                        )
                                    }
                                />
                                <PrimaryButton
                                    type="submit"
                                    disabled={loyaltyForm.processing}
                                >
                                    Submit
                                </PrimaryButton>
                            </form>

                            <div className="mt-6 divide-y divide-gray-100 dark:divide-gray-700">
                                {loyaltyTransactions.map((tx) => (
                                    <div
                                        key={tx.id}
                                        className="flex items-center justify-between py-2 text-sm"
                                    >
                                        <div>
                                            <Badge
                                                variant={
                                                    tx.points >= 0
                                                        ? 'success'
                                                        : 'danger'
                                                }
                                            >
                                                {tx.type}
                                            </Badge>
                                            {tx.notes && (
                                                <span className="ml-2 text-gray-500 dark:text-gray-400">
                                                    {tx.notes}
                                                </span>
                                            )}
                                        </div>
                                        <span
                                            className={
                                                tx.points >= 0
                                                    ? 'text-emerald-600'
                                                    : 'text-red-600'
                                            }
                                        >
                                            {tx.points >= 0 ? '+' : ''}
                                            {tx.points}
                                        </span>
                                    </div>
                                ))}
                                {loyaltyTransactions.length === 0 && (
                                    <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No loyalty activity yet.
                                    </p>
                                )}
                            </div>
                        </Card>

                        <Card title="Notes Timeline">
                            <form onSubmit={submitNote} className="space-y-3">
                                <textarea
                                    placeholder="Add a note about this customer..."
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    rows={3}
                                    value={noteForm.data.body}
                                    onChange={(e) =>
                                        noteForm.setData('body', e.target.value)
                                    }
                                />
                                <InputError message={noteForm.errors.body} />
                                <div className="flex justify-end">
                                    <SecondaryButton
                                        type="submit"
                                        disabled={noteForm.processing}
                                    >
                                        Add Note
                                    </SecondaryButton>
                                </div>
                            </form>

                            <div className="mt-6 space-y-4">
                                {notes.map((note) => (
                                    <div
                                        key={note.id}
                                        className="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900/40"
                                    >
                                        <p className="text-gray-900 dark:text-gray-100">
                                            {note.body}
                                        </p>
                                        <div className="mt-1 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                            <span>
                                                {note.author?.name ?? 'Unknown'}{' '}
                                                · {note.created_at}
                                            </span>
                                            <button
                                                onClick={() => deleteNote(note)}
                                                className="text-red-600 hover:underline"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                ))}
                                {notes.length === 0 && (
                                    <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No notes yet.
                                    </p>
                                )}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
