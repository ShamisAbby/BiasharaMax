import Badge from '@/Components/Badge';
import Checkbox from '@/Components/Checkbox';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import CrmLayout from '@/Layouts/CrmLayout';
import { LoyaltyReward } from '@/types/crm';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface RewardFormData {
    name: string;
    description: string;
    points_cost: string;
    stock_quantity: string;
    is_active: boolean;
}

const emptyForm: RewardFormData = {
    name: '',
    description: '',
    points_cost: '',
    stock_quantity: '',
    is_active: true,
};

export default function RewardsIndex({
    rewards,
}: {
    rewards: LoyaltyReward[];
}) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<LoyaltyReward | null>(null);

    const createForm = useForm<RewardFormData>(emptyForm);
    const editForm = useForm<RewardFormData>(emptyForm);

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('crm.loyalty-rewards.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const openEdit = (reward: LoyaltyReward) => {
        editForm.setData({
            name: reward.name,
            description: reward.description ?? '',
            points_cost: String(reward.points_cost),
            stock_quantity:
                reward.stock_quantity === null
                    ? ''
                    : String(reward.stock_quantity),
            is_active: reward.is_active,
        });
        setEditing(reward);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('crm.loyalty-rewards.update', editing.id), {
            onSuccess: () => setEditing(null),
        });
    };

    const destroy = (reward: LoyaltyReward) => {
        askConfirm({
            title: `Delete the "${reward.name}" reward?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('crm.loyalty-rewards.destroy', reward.id));
            },
        });
    };

    return (
        <CrmLayout title="Loyalty Rewards">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Reward Catalog
                </h3>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Add Reward
                </PrimaryButton>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Name',
                                'Points Cost',
                                'Stock',
                                'Redemptions',
                                'Status',
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
                        {rewards.map((reward) => (
                            <tr
                                key={reward.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {reward.name}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {reward.points_cost} pts
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {reward.stock_quantity === null
                                        ? 'Unlimited'
                                        : reward.stock_quantity}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {reward.redemptions_count ?? 0}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    {reward.is_active ? (
                                        reward.in_stock ? (
                                            <Badge variant="success">
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge variant="warning">
                                                Out of stock
                                            </Badge>
                                        )
                                    ) : (
                                        <Badge variant="neutral">
                                            Inactive
                                        </Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <button
                                        onClick={() => openEdit(reward)}
                                        className="mr-3 text-indigo-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => destroy(reward)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {rewards.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No rewards yet. Add one customers can redeem with
                        loyalty points.
                    </p>
                )}
            </div>

            <Modal show={creating} onClose={() => setCreating(false)}>
                <RewardForm
                    form={createForm}
                    onSubmit={submitCreate}
                    onCancel={() => setCreating(false)}
                    submitLabel="Add Reward"
                />
            </Modal>

            <Modal show={editing !== null} onClose={() => setEditing(null)}>
                <RewardForm
                    form={editForm}
                    onSubmit={submitEdit}
                    onCancel={() => setEditing(null)}
                    submitLabel="Save Changes"
                />
            </Modal>
        </CrmLayout>
    );
}

function RewardForm({
    form,
    onSubmit,
    onCancel,
    submitLabel,
}: {
    form: ReturnType<typeof useForm<RewardFormData>>;
    onSubmit: (e: FormEvent) => void;
    onCancel: () => void;
    submitLabel: string;
}) {
    return (
        <form onSubmit={onSubmit} className="p-6">
            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                Reward details
            </h2>
            <div className="mt-4 space-y-4">
                <div>
                    <TextInput
                        placeholder="Name"
                        className="block w-full"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    <InputError message={form.errors.name} className="mt-2" />
                </div>
                <textarea
                    placeholder="Description"
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    rows={2}
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                />
                <div>
                    <TextInput
                        type="number"
                        placeholder="Points cost"
                        className="block w-full"
                        value={form.data.points_cost}
                        onChange={(e) =>
                            form.setData('points_cost', e.target.value)
                        }
                    />
                    <InputError
                        message={form.errors.points_cost}
                        className="mt-2"
                    />
                </div>
                <TextInput
                    type="number"
                    placeholder="Stock quantity (leave blank for unlimited)"
                    className="block w-full"
                    value={form.data.stock_quantity}
                    onChange={(e) =>
                        form.setData('stock_quantity', e.target.value)
                    }
                />
                <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <Checkbox
                        checked={form.data.is_active}
                        onChange={(e) =>
                            form.setData('is_active', e.target.checked)
                        }
                    />
                    Active
                </label>
            </div>
            <div className="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" onClick={onCancel}>
                    Cancel
                </SecondaryButton>
                <PrimaryButton type="submit" disabled={form.processing}>
                    {submitLabel}
                </PrimaryButton>
            </div>
        </form>
    );
}
