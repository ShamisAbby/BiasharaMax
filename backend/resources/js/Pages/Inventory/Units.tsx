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
import { Unit } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function UnitForm({
    unit,
    units,
    onSaved,
}: {
    unit?: Unit;
    units: Unit[];
    onSaved: () => void;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        base_unit_id: unit?.base_unit_id ?? '',
        name: unit?.name ?? '',
        symbol: unit?.symbol ?? '',
        conversion_factor: unit?.conversion_factor ?? '1',
        status: unit?.status ?? 'active',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { onSuccess: onSaved };

        if (unit) {
            patch(route('inventory.units.update', unit.id), options);
        } else {
            post(route('inventory.units.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="unit_name" value="Name" />
                <TextInput
                    id="unit_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel
                    htmlFor="unit_symbol"
                    value="Symbol (e.g. kg, pc)"
                />
                <TextInput
                    id="unit_symbol"
                    className="mt-1 block w-full"
                    value={data.symbol}
                    onChange={(e) => setData('symbol', e.target.value)}
                    required
                />
                <InputError message={errors.symbol} className="mt-2" />
            </div>

            <div>
                <InputLabel
                    htmlFor="unit_base"
                    value="Base unit (optional, for conversions)"
                />
                <SelectInput
                    id="unit_base"
                    className="mt-1 block w-full"
                    value={data.base_unit_id}
                    onChange={(e) => setData('base_unit_id', e.target.value)}
                >
                    <option value="">None</option>
                    {units
                        .filter((u) => u.id !== unit?.id)
                        .map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name} ({u.symbol})
                            </option>
                        ))}
                </SelectInput>
            </div>

            <div>
                <InputLabel
                    htmlFor="unit_factor"
                    value="Conversion factor (relative to base unit)"
                />
                <TextInput
                    id="unit_factor"
                    type="number"
                    step="0.0001"
                    className="mt-1 block w-full"
                    value={data.conversion_factor}
                    onChange={(e) =>
                        setData('conversion_factor', e.target.value)
                    }
                    required
                />
                <InputError
                    message={errors.conversion_factor}
                    className="mt-2"
                />
            </div>

            <div>
                <InputLabel htmlFor="unit_status" value="Status" />
                <SelectInput
                    id="unit_status"
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
                    {unit ? 'Save changes' : 'Create unit'}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Units({ units }: { units: Unit[] }) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Unit | null>(null);

    const destroy = (unit: Unit) => {
        askConfirm({
            title: `Delete the "${unit.name}" unit?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('inventory.units.destroy', unit.id));
            },
        });
    };

    return (
        <InventoryLayout title="Units">
            <Card
                title="Units of measurement"
                description="Define how stock is counted (pieces, kg, boxes, etc.) and conversions between them."
                actions={
                    <PrimaryButton onClick={() => setCreating(true)}>
                        New unit
                    </PrimaryButton>
                }
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {units.map((unit) => (
                        <div
                            key={unit.id}
                            className="flex items-center justify-between py-3"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {unit.name} ({unit.symbol})
                                    </p>
                                    {unit.status === 'inactive' && (
                                        <Badge variant="warning">
                                            Inactive
                                        </Badge>
                                    )}
                                </div>
                                {unit.base_unit_name && (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        1 {unit.symbol} ={' '}
                                        {unit.conversion_factor}{' '}
                                        {unit.base_unit_name}
                                    </p>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <SecondaryButton
                                    onClick={() => setEditing(unit)}
                                >
                                    Edit
                                </SecondaryButton>
                                <DangerButton onClick={() => destroy(unit)}>
                                    Delete
                                </DangerButton>
                            </div>
                        </div>
                    ))}
                    {units.length === 0 && (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No units yet.
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
                        New unit
                    </h2>
                    <div className="mt-4">
                        <UnitForm
                            units={units}
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
                        Edit unit
                    </h2>
                    <div className="mt-4">
                        {editing && (
                            <UnitForm
                                unit={editing}
                                units={units}
                                onSaved={() => setEditing(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </InventoryLayout>
    );
}
