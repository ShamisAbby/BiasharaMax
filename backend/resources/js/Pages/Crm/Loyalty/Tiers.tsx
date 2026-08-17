import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import CrmLayout from '@/Layouts/CrmLayout';
import { LoyaltyTier } from '@/types/crm';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface TierFormData {
    name: string;
    minimum_spend: string;
    sort_order: string;
    benefits_description: string;
}

const emptyForm: TierFormData = {
    name: '',
    minimum_spend: '0',
    sort_order: '0',
    benefits_description: '',
};

export default function TiersIndex({ tiers }: { tiers: LoyaltyTier[] }) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<LoyaltyTier | null>(null);

    const createForm = useForm<TierFormData>(emptyForm);
    const editForm = useForm<TierFormData>(emptyForm);

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('crm.loyalty-tiers.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const openEdit = (tier: LoyaltyTier) => {
        editForm.setData({
            name: tier.name,
            minimum_spend: tier.minimum_spend,
            sort_order: String(tier.sort_order),
            benefits_description: tier.benefits_description ?? '',
        });
        setEditing(tier);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('crm.loyalty-tiers.update', editing.id), {
            onSuccess: () => setEditing(null),
        });
    };

    const destroy = (tier: LoyaltyTier) => {
        askConfirm({
            title: `Delete the "${tier.name}" tier?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('crm.loyalty-tiers.destroy', tier.id));
            },
        });
    };

    return (
        <CrmLayout title="Loyalty Tiers">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Membership Tiers
                </h3>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Add Tier
                </PrimaryButton>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Name',
                                'Minimum Spend',
                                'Benefits',
                                'Customers',
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
                        {tiers.map((tier) => (
                            <tr
                                key={tier.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {tier.name}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {tier.minimum_spend}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {tier.benefits_description ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {tier.customers_count ?? 0}
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <button
                                        onClick={() => openEdit(tier)}
                                        className="mr-3 text-indigo-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => destroy(tier)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {tiers.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No tiers yet. Add one to start grouping customers by
                        lifetime spend.
                    </p>
                )}
            </div>

            <Modal show={creating} onClose={() => setCreating(false)}>
                <TierForm
                    form={createForm}
                    onSubmit={submitCreate}
                    onCancel={() => setCreating(false)}
                    submitLabel="Add Tier"
                />
            </Modal>

            <Modal show={editing !== null} onClose={() => setEditing(null)}>
                <TierForm
                    form={editForm}
                    onSubmit={submitEdit}
                    onCancel={() => setEditing(null)}
                    submitLabel="Save Changes"
                />
            </Modal>
        </CrmLayout>
    );
}

function TierForm({
    form,
    onSubmit,
    onCancel,
    submitLabel,
}: {
    form: ReturnType<typeof useForm<TierFormData>>;
    onSubmit: (e: FormEvent) => void;
    onCancel: () => void;
    submitLabel: string;
}) {
    return (
        <form onSubmit={onSubmit} className="p-6">
            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                Tier details
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
                <div>
                    <TextInput
                        type="number"
                        step="0.01"
                        placeholder="Minimum lifetime spend"
                        className="block w-full"
                        value={form.data.minimum_spend}
                        onChange={(e) =>
                            form.setData('minimum_spend', e.target.value)
                        }
                    />
                    <InputError
                        message={form.errors.minimum_spend}
                        className="mt-2"
                    />
                </div>
                <TextInput
                    type="number"
                    placeholder="Sort order"
                    className="block w-full"
                    value={form.data.sort_order}
                    onChange={(e) => form.setData('sort_order', e.target.value)}
                />
                <textarea
                    placeholder="Benefits description"
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    rows={3}
                    value={form.data.benefits_description}
                    onChange={(e) =>
                        form.setData('benefits_description', e.target.value)
                    }
                />
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
