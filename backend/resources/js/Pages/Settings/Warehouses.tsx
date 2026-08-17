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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Branch, Warehouse } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function WarehouseForm({
    warehouse,
    branches,
    onSaved,
}: {
    warehouse?: Warehouse;
    branches: Branch[];
    onSaved: () => void;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        branch_id: warehouse?.branch_id ?? branches[0]?.id ?? '',
        name: warehouse?.name ?? '',
        code: warehouse?.code ?? '',
        address: warehouse?.address ?? '',
        status: warehouse?.status ?? 'active',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { onSuccess: onSaved };

        if (warehouse) {
            patch(route('settings.warehouses.update', warehouse.id), options);
        } else {
            post(route('settings.warehouses.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="warehouse_branch" value="Branch" />
                <SelectInput
                    id="warehouse_branch"
                    className="mt-1 block w-full"
                    value={data.branch_id}
                    onChange={(e) => setData('branch_id', e.target.value)}
                    required
                >
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </SelectInput>
                <InputError message={errors.branch_id} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="warehouse_name" value="Warehouse name" />
                <TextInput
                    id="warehouse_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="warehouse_code" value="Code" />
                <TextInput
                    id="warehouse_code"
                    className="mt-1 block w-full uppercase"
                    value={data.code}
                    onChange={(e) =>
                        setData('code', e.target.value.toUpperCase())
                    }
                    required
                    disabled={warehouse?.is_default}
                />
                <InputError message={errors.code} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="warehouse_address" value="Address" />
                <TextInput
                    id="warehouse_address"
                    className="mt-1 block w-full"
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                />
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>
                    {warehouse ? 'Save changes' : 'Create warehouse'}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Warehouses({
    warehouses,
    branches,
}: {
    warehouses: Warehouse[];
    branches: Branch[];
}) {
    const askConfirm = useConfirm();
    const [editingWarehouse, setEditingWarehouse] = useState<Warehouse | null>(
        null,
    );
    const [creating, setCreating] = useState(false);

    const deleteWarehouse = (warehouse: Warehouse) => {
        askConfirm({
            title: `Delete the "${warehouse.name}" warehouse?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('settings.warehouses.destroy', warehouse.id),
                );
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Warehouses
                </h2>
            }
        >
            <Head title="Warehouses" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <Card
                        title="Warehouses"
                        description="Where stock is held within each branch."
                        actions={
                            <PrimaryButton
                                onClick={() => setCreating(true)}
                                disabled={branches.length === 0}
                            >
                                New warehouse
                            </PrimaryButton>
                        }
                    >
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {warehouses.map((warehouse) => (
                                <div
                                    key={warehouse.id}
                                    className="flex items-center justify-between py-3"
                                >
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                {warehouse.name}
                                            </p>
                                            <Badge variant="neutral">
                                                {warehouse.code}
                                            </Badge>
                                            {warehouse.is_default && (
                                                <Badge variant="info">
                                                    Default
                                                </Badge>
                                            )}
                                            {warehouse.status ===
                                                'inactive' && (
                                                <Badge variant="warning">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            {warehouse.branch_name}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <SecondaryButton
                                            onClick={() =>
                                                setEditingWarehouse(warehouse)
                                            }
                                        >
                                            Edit
                                        </SecondaryButton>
                                        {!warehouse.is_default && (
                                            <DangerButton
                                                onClick={() =>
                                                    deleteWarehouse(warehouse)
                                                }
                                            >
                                                Delete
                                            </DangerButton>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>
            </div>

            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        New warehouse
                    </h2>
                    <div className="mt-4">
                        <WarehouseForm
                            branches={branches}
                            onSaved={() => setCreating(false)}
                        />
                    </div>
                </div>
            </Modal>

            <Modal
                show={editingWarehouse !== null}
                onClose={() => setEditingWarehouse(null)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Edit warehouse
                    </h2>
                    <div className="mt-4">
                        {editingWarehouse && (
                            <WarehouseForm
                                warehouse={editingWarehouse}
                                branches={branches}
                                onSaved={() => setEditingWarehouse(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
