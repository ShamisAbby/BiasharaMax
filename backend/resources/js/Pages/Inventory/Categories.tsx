import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import { useConfirm } from '@/Components/ConfirmDialog';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import InventoryLayout from '@/Layouts/InventoryLayout';
import { Category } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function CategoryForm({
    category,
    categories,
    onSaved,
}: {
    category?: Category;
    categories: Category[];
    onSaved: () => void;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        parent_id: category?.parent_id ?? '',
        name: category?.name ?? '',
        description: category?.description ?? '',
        status: category?.status ?? 'active',
        sort_order: category?.sort_order ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { onSuccess: onSaved };

        if (category) {
            patch(route('inventory.categories.update', category.id), options);
        } else {
            post(route('inventory.categories.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="category_name" value="Name" />
                <TextInput
                    id="category_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel
                    htmlFor="category_parent"
                    value="Parent category (optional)"
                />
                <SelectInput
                    id="category_parent"
                    className="mt-1 block w-full"
                    value={data.parent_id}
                    onChange={(e) => setData('parent_id', e.target.value)}
                >
                    <option value="">None</option>
                    {categories
                        .filter((c) => c.id !== category?.id)
                        .map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                </SelectInput>
                <InputError message={errors.parent_id} className="mt-2" />
            </div>

            <div>
                <InputLabel
                    htmlFor="category_description"
                    value="Description"
                />
                <TextInput
                    id="category_description"
                    className="mt-1 block w-full"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
            </div>

            <div>
                <InputLabel htmlFor="category_status" value="Status" />
                <SelectInput
                    id="category_status"
                    className="mt-1 block w-full"
                    value={data.status}
                    onChange={(e) =>
                        setData(
                            'status',
                            e.target.value as 'active' | 'inactive',
                        )
                    }
                >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </SelectInput>
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>
                    {category ? 'Save changes' : 'Create category'}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Categories({ categories }: { categories: Category[] }) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);

    const destroy = (category: Category) => {
        askConfirm({
            title: `Delete the "${category.name}" category?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('inventory.categories.destroy', category.id),
                );
            },
        });
    };

    return (
        <InventoryLayout title="Categories">
            <Card
                title="Categories"
                description="Organize products into categories and subcategories."
                actions={
                    <PrimaryButton onClick={() => setCreating(true)}>
                        New category
                    </PrimaryButton>
                }
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {categories.map((category) => (
                        <div
                            key={category.id}
                            className="flex items-center justify-between py-3"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {category.parent_id && (
                                            <span className="text-gray-400">
                                                —{' '}
                                            </span>
                                        )}
                                        {category.name}
                                    </p>
                                    {category.status === 'inactive' && (
                                        <Badge variant="warning">
                                            Inactive
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {category.products_count ?? 0} product
                                    {category.products_count === 1 ? '' : 's'}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <SecondaryButton
                                    onClick={() => setEditing(category)}
                                >
                                    Edit
                                </SecondaryButton>
                                <DangerButton onClick={() => destroy(category)}>
                                    Delete
                                </DangerButton>
                            </div>
                        </div>
                    ))}
                    {categories.length === 0 && (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No categories yet.
                        </p>
                    )}
                </div>
            </Card>

            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        New category
                    </h2>
                    <div className="mt-4">
                        <CategoryForm
                            categories={categories}
                            onSaved={() => setCreating(false)}
                        />
                    </div>
                </div>
            </Modal>

            <Modal
                show={editing !== null}
                onClose={() => setEditing(null)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Edit category
                    </h2>
                    <div className="mt-4">
                        {editing && (
                            <CategoryForm
                                category={editing}
                                categories={categories}
                                onSaved={() => setEditing(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </InventoryLayout>
    );
}
