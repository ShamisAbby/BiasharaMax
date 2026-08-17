import Badge from '@/Components/Badge';
import Checkbox from '@/Components/Checkbox';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AccountingLayout from '@/Layouts/AccountingLayout';
import { ExpenseCategory } from '@/types/accounting';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export default function ExpenseCategoriesIndex({
    categories,
}: {
    categories: ExpenseCategory[];
}) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<ExpenseCategory | null>(null);

    const createForm = useForm({
        name: '',
        description: '',
        is_active: true as boolean,
    });
    const editForm = useForm({
        name: '',
        description: '',
        is_active: true as boolean,
    });

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('accounting.expense-categories.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const openEdit = (category: ExpenseCategory) => {
        editForm.setData({
            name: category.name,
            description: category.description ?? '',
            is_active: category.is_active,
        });
        setEditing(category);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(
            route('accounting.expense-categories.update', editing.id),
            {
                onSuccess: () => setEditing(null),
            },
        );
    };

    const destroy = (category: ExpenseCategory) => {
        askConfirm({
            title: `Delete the "${category.name}" category?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('accounting.expense-categories.destroy', category.id),
                );
            },
        });
    };

    return (
        <AccountingLayout title="Expense Categories">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Expense Categories
                </h3>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Add Category
                </PrimaryButton>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Name',
                                'Description',
                                'Expenses',
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
                        {categories.map((category) => (
                            <tr
                                key={category.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {category.name}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {category.description ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {category.expenses_count ?? 0}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={
                                            category.is_active
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {category.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <button
                                        onClick={() => openEdit(category)}
                                        className="mr-3 text-indigo-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => destroy(category)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {categories.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No expense categories yet. Add one to start organizing
                        your expenses.
                    </p>
                )}
            </div>

            <Modal show={creating} onClose={() => setCreating(false)}>
                <CategoryForm
                    form={createForm}
                    onSubmit={submitCreate}
                    onCancel={() => setCreating(false)}
                    submitLabel="Add Category"
                />
            </Modal>

            <Modal show={editing !== null} onClose={() => setEditing(null)}>
                <CategoryForm
                    form={editForm}
                    onSubmit={submitEdit}
                    onCancel={() => setEditing(null)}
                    submitLabel="Save Changes"
                />
            </Modal>
        </AccountingLayout>
    );
}

function CategoryForm({
    form,
    onSubmit,
    onCancel,
    submitLabel,
}: {
    form: ReturnType<
        typeof useForm<{
            name: string;
            description: string;
            is_active: boolean;
        }>
    >;
    onSubmit: (e: FormEvent) => void;
    onCancel: () => void;
    submitLabel: string;
}) {
    return (
        <form onSubmit={onSubmit} className="p-6">
            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                Category details
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
