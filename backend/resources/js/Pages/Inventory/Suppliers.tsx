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
import { Supplier } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function SupplierForm({
    supplier,
    onSaved,
}: {
    supplier?: Supplier;
    onSaved: () => void;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: supplier?.name ?? '',
        email: supplier?.email ?? '',
        phone: supplier?.phone ?? '',
        address: supplier?.address ?? '',
        status: supplier?.status ?? 'active',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { onSuccess: onSaved };

        if (supplier) {
            patch(route('inventory.suppliers.update', supplier.id), options);
        } else {
            post(route('inventory.suppliers.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="supplier_name" value="Name" />
                <TextInput
                    id="supplier_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="supplier_email" value="Email" />
                    <TextInput
                        id="supplier_email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>
                <div>
                    <InputLabel htmlFor="supplier_phone" value="Phone" />
                    <TextInput
                        id="supplier_phone"
                        className="mt-1 block w-full"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="supplier_address" value="Address" />
                <TextInput
                    id="supplier_address"
                    className="mt-1 block w-full"
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                />
            </div>

            <div>
                <InputLabel htmlFor="supplier_status" value="Status" />
                <SelectInput
                    id="supplier_status"
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
                    {supplier ? 'Save changes' : 'Create supplier'}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Suppliers({ suppliers }: { suppliers: Supplier[] }) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Supplier | null>(null);

    const destroy = (supplier: Supplier) => {
        askConfirm({
            title: `Delete the "${supplier.name}" supplier?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('inventory.suppliers.destroy', supplier.id),
                );
            },
        });
    };

    return (
        <InventoryLayout title="Suppliers">
            <Card
                title="Suppliers"
                description="Vendors you purchase products from."
                actions={
                    <PrimaryButton onClick={() => setCreating(true)}>
                        New supplier
                    </PrimaryButton>
                }
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {suppliers.map((supplier) => (
                        <div
                            key={supplier.id}
                            className="flex items-center justify-between py-3"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {supplier.name}
                                    </p>
                                    {supplier.status === 'inactive' && (
                                        <Badge variant="warning">
                                            Inactive
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {[supplier.email, supplier.phone]
                                        .filter(Boolean)
                                        .join(' · ') || 'No contact info'}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <SecondaryButton
                                    onClick={() => setEditing(supplier)}
                                >
                                    Edit
                                </SecondaryButton>
                                <DangerButton onClick={() => destroy(supplier)}>
                                    Delete
                                </DangerButton>
                            </div>
                        </div>
                    ))}
                    {suppliers.length === 0 && (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No suppliers yet.
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
                        New supplier
                    </h2>
                    <div className="mt-4">
                        <SupplierForm onSaved={() => setCreating(false)} />
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
                        Edit supplier
                    </h2>
                    <div className="mt-4">
                        {editing && (
                            <SupplierForm
                                supplier={editing}
                                onSaved={() => setEditing(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </InventoryLayout>
    );
}
