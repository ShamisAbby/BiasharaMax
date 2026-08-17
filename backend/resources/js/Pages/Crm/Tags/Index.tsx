import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import CrmLayout from '@/Layouts/CrmLayout';
import { CustomerTag } from '@/types/crm';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface TagFormData {
    name: string;
    color: string;
}

const emptyForm: TagFormData = { name: '', color: '#6366F1' };

export default function TagsIndex({ tags }: { tags: CustomerTag[] }) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<CustomerTag | null>(null);

    const createForm = useForm<TagFormData>(emptyForm);
    const editForm = useForm<TagFormData>(emptyForm);

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('crm.customer-tags.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const openEdit = (tag: CustomerTag) => {
        editForm.setData({ name: tag.name, color: tag.color ?? '#6366F1' });
        setEditing(tag);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('crm.customer-tags.update', editing.id), {
            onSuccess: () => setEditing(null),
        });
    };

    const destroy = (tag: CustomerTag) => {
        askConfirm({
            title: `Delete the "${tag.name}" tag?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('crm.customer-tags.destroy', tag.id));
            },
        });
    };

    return (
        <CrmLayout title="Customer Tags">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Customer Tags
                </h3>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Add Tag
                </PrimaryButton>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {['Tag', 'Customers', ''].map((h) => (
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
                        {tags.map((tag) => (
                            <tr
                                key={tag.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm">
                                    <span
                                        className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                        style={{
                                            backgroundColor:
                                                tag.color ?? '#6366F1',
                                        }}
                                    >
                                        {tag.name}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {tag.customers_count ?? 0}
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <button
                                        onClick={() => openEdit(tag)}
                                        className="mr-3 text-indigo-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => destroy(tag)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {tags.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No customer tags yet. Add one to start labeling
                        customers.
                    </p>
                )}
            </div>

            <Modal show={creating} onClose={() => setCreating(false)}>
                <TagForm
                    form={createForm}
                    onSubmit={submitCreate}
                    onCancel={() => setCreating(false)}
                    submitLabel="Add Tag"
                />
            </Modal>

            <Modal show={editing !== null} onClose={() => setEditing(null)}>
                <TagForm
                    form={editForm}
                    onSubmit={submitEdit}
                    onCancel={() => setEditing(null)}
                    submitLabel="Save Changes"
                />
            </Modal>
        </CrmLayout>
    );
}

function TagForm({
    form,
    onSubmit,
    onCancel,
    submitLabel,
}: {
    form: ReturnType<typeof useForm<TagFormData>>;
    onSubmit: (e: FormEvent) => void;
    onCancel: () => void;
    submitLabel: string;
}) {
    return (
        <form onSubmit={onSubmit} className="p-6">
            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                Tag details
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
                <div className="flex items-center gap-3">
                    <input
                        type="color"
                        value={form.data.color}
                        onChange={(e) => form.setData('color', e.target.value)}
                        className="h-9 w-14 rounded border border-gray-300 dark:border-gray-700"
                    />
                    <span className="text-sm text-gray-500 dark:text-gray-400">
                        {form.data.color}
                    </span>
                </div>
                <InputError message={form.errors.color} className="mt-2" />
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
