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
import { Brand } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function BrandForm({ brand, onSaved }: { brand?: Brand; onSaved: () => void }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: brand?.name ?? '',
        description: brand?.description ?? '',
        status: brand?.status ?? 'active',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { onSuccess: onSaved };

        if (brand) {
            patch(route('inventory.brands.update', brand.id), options);
        } else {
            post(route('inventory.brands.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="brand_name" value="Name" />
                <TextInput
                    id="brand_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="brand_description" value="Description" />
                <TextInput
                    id="brand_description"
                    className="mt-1 block w-full"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
            </div>

            <div>
                <InputLabel htmlFor="brand_status" value="Status" />
                <SelectInput
                    id="brand_status"
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
                    {brand ? 'Save changes' : 'Create brand'}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Brands({ brands }: { brands: Brand[] }) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Brand | null>(null);

    const destroy = (brand: Brand) => {
        askConfirm({
            title: `Delete the "${brand.name}" brand?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('inventory.brands.destroy', brand.id));
            },
        });
    };

    return (
        <InventoryLayout title="Brands">
            <Card
                title="Brands"
                description="Manufacturers and brands carried by your business."
                actions={
                    <PrimaryButton onClick={() => setCreating(true)}>
                        New brand
                    </PrimaryButton>
                }
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {brands.map((brand) => (
                        <div
                            key={brand.id}
                            className="flex items-center justify-between py-3"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {brand.name}
                                    </p>
                                    {brand.status === 'inactive' && (
                                        <Badge variant="warning">
                                            Inactive
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {brand.products_count ?? 0} product
                                    {brand.products_count === 1 ? '' : 's'}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <SecondaryButton
                                    onClick={() => setEditing(brand)}
                                >
                                    Edit
                                </SecondaryButton>
                                <DangerButton onClick={() => destroy(brand)}>
                                    Delete
                                </DangerButton>
                            </div>
                        </div>
                    ))}
                    {brands.length === 0 && (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No brands yet.
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
                        New brand
                    </h2>
                    <div className="mt-4">
                        <BrandForm onSaved={() => setCreating(false)} />
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
                        Edit brand
                    </h2>
                    <div className="mt-4">
                        {editing && (
                            <BrandForm
                                brand={editing}
                                onSaved={() => setEditing(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </InventoryLayout>
    );
}
