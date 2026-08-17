import Badge from '@/Components/Badge';
import Checkbox from '@/Components/Checkbox';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import CrmLayout from '@/Layouts/CrmLayout';
import { CustomerGroup } from '@/types/crm';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface GroupFormData {
    name: string;
    description: string;
    is_vip: boolean;
    discount_percentage: string;
}

const emptyForm: GroupFormData = {
    name: '',
    description: '',
    is_vip: false,
    discount_percentage: '0',
};

export default function GroupsIndex({ groups }: { groups: CustomerGroup[] }) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<CustomerGroup | null>(null);

    const createForm = useForm<GroupFormData>(emptyForm);
    const editForm = useForm<GroupFormData>(emptyForm);

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('crm.customer-groups.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const openEdit = (group: CustomerGroup) => {
        editForm.setData({
            name: group.name,
            description: group.description ?? '',
            is_vip: group.is_vip,
            discount_percentage: group.discount_percentage,
        });
        setEditing(group);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('crm.customer-groups.update', editing.id), {
            onSuccess: () => setEditing(null),
        });
    };

    const destroy = (group: CustomerGroup) => {
        askConfirm({
            title: `Delete the "${group.name}" group?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('crm.customer-groups.destroy', group.id));
            },
        });
    };

    return (
        <CrmLayout title="Customer Groups">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Customer Groups
                </h3>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Add Group
                </PrimaryButton>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Name',
                                'Description',
                                'Discount',
                                'Customers',
                                'VIP',
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
                        {groups.map((group) => (
                            <tr
                                key={group.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {group.name}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {group.description ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {group.discount_percentage}%
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {group.customers_count ?? 0}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    {group.is_vip && (
                                        <Badge variant="warning">VIP</Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <button
                                        onClick={() => openEdit(group)}
                                        className="mr-3 text-indigo-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => destroy(group)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {groups.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No customer groups yet. Add one to start segmenting
                        customers.
                    </p>
                )}
            </div>

            <Modal show={creating} onClose={() => setCreating(false)}>
                <GroupForm
                    form={createForm}
                    onSubmit={submitCreate}
                    onCancel={() => setCreating(false)}
                    submitLabel="Add Group"
                />
            </Modal>

            <Modal show={editing !== null} onClose={() => setEditing(null)}>
                <GroupForm
                    form={editForm}
                    onSubmit={submitEdit}
                    onCancel={() => setEditing(null)}
                    submitLabel="Save Changes"
                />
            </Modal>
        </CrmLayout>
    );
}

function GroupForm({
    form,
    onSubmit,
    onCancel,
    submitLabel,
}: {
    form: ReturnType<typeof useForm<GroupFormData>>;
    onSubmit: (e: FormEvent) => void;
    onCancel: () => void;
    submitLabel: string;
}) {
    return (
        <form onSubmit={onSubmit} className="p-6">
            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                Group details
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
                <TextInput
                    placeholder="Description"
                    className="block w-full"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                />
                <TextInput
                    type="number"
                    step="0.01"
                    placeholder="Discount %"
                    className="block w-full"
                    value={form.data.discount_percentage}
                    onChange={(e) =>
                        form.setData('discount_percentage', e.target.value)
                    }
                />
                <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <Checkbox
                        checked={form.data.is_vip}
                        onChange={(e) =>
                            form.setData('is_vip', e.target.checked)
                        }
                    />
                    VIP segment
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
