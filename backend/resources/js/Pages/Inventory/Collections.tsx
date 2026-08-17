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
import { ProductCollection } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function CollectionForm({
    collection,
    onSaved,
}: {
    collection?: ProductCollection;
    onSaved: () => void;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: collection?.name ?? '',
        description: collection?.description ?? '',
        status: collection?.status ?? 'active',
        sort_order: collection?.sort_order ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { onSuccess: onSaved };

        if (collection) {
            patch(
                route('inventory.collections.update', collection.id),
                options,
            );
        } else {
            post(route('inventory.collections.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="collection_name" value="Name" />
                <TextInput
                    id="collection_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel
                    htmlFor="collection_description"
                    value="Description"
                />
                <TextInput
                    id="collection_description"
                    className="mt-1 block w-full"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
            </div>

            <div>
                <InputLabel htmlFor="collection_status" value="Status" />
                <SelectInput
                    id="collection_status"
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
                    {collection ? 'Save changes' : 'Create collection'}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Collections({
    collections,
}: {
    collections: ProductCollection[];
}) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<ProductCollection | null>(null);

    const destroy = (collection: ProductCollection) => {
        askConfirm({
            title: `Delete the "${collection.name}" collection?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('inventory.collections.destroy', collection.id),
                );
            },
        });
    };

    return (
        <InventoryLayout title="Collections">
            <Card
                title="Collections"
                description="Curated merchandising groups (e.g. seasonal promotions) distinct from categories."
                actions={
                    <PrimaryButton onClick={() => setCreating(true)}>
                        New collection
                    </PrimaryButton>
                }
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {collections.map((collection) => (
                        <div
                            key={collection.id}
                            className="flex items-center justify-between py-3"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {collection.name}
                                    </p>
                                    {collection.status === 'inactive' && (
                                        <Badge variant="warning">
                                            Inactive
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {collection.products_count ?? 0} product
                                    {collection.products_count === 1 ? '' : 's'}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <SecondaryButton
                                    onClick={() => setEditing(collection)}
                                >
                                    Edit
                                </SecondaryButton>
                                <DangerButton
                                    onClick={() => destroy(collection)}
                                >
                                    Delete
                                </DangerButton>
                            </div>
                        </div>
                    ))}
                    {collections.length === 0 && (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No collections yet.
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
                        New collection
                    </h2>
                    <div className="mt-4">
                        <CollectionForm onSaved={() => setCreating(false)} />
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
                        Edit collection
                    </h2>
                    <div className="mt-4">
                        {editing && (
                            <CollectionForm
                                collection={editing}
                                onSaved={() => setEditing(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </InventoryLayout>
    );
}
