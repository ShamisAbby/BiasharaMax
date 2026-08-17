import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import InventoryLayout from '@/Layouts/InventoryLayout';
import { Warehouse } from '@/types';
import { InventoryCount, InventoryCountItem } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function StartCountForm({
    warehouses,
    onSaved,
}: {
    warehouses: Warehouse[];
    onSaved: () => void;
}) {
    const { data, setData, post, processing } = useForm({
        warehouse_id: warehouses[0]?.id ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('inventory.counts.store'), { onSuccess: onSaved });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="count_warehouse" value="Warehouse" />
                <SelectInput
                    id="count_warehouse"
                    className="mt-1 block w-full"
                    value={data.warehouse_id}
                    onChange={(e) => setData('warehouse_id', e.target.value)}
                >
                    {warehouses.map((warehouse) => (
                        <option key={warehouse.id} value={warehouse.id}>
                            {warehouse.name}
                        </option>
                    ))}
                </SelectInput>
            </div>
            <p className="text-sm text-gray-500">
                This snapshots the current system quantity for every product in
                this warehouse. You'll enter the physically counted quantity for
                each one next.
            </p>
            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>Start count</PrimaryButton>
            </div>
        </form>
    );
}

function CountItemRow({ item }: { item: InventoryCountItem }) {
    const [value, setValue] = useState(item.counted_quantity ?? '');
    const [saved, setSaved] = useState(item.counted_quantity !== null);

    const save = () => {
        router.patch(
            route('inventory.counts.items.record', item.id),
            { counted_quantity: value },
            { onSuccess: () => setSaved(true), preserveScroll: true },
        );
    };

    return (
        <tr>
            <td className="py-2 pr-4 text-sm">{item.product_name}</td>
            <td className="py-2 pr-4 text-sm">{item.expected_quantity}</td>
            <td className="py-2 pr-4">
                <div className="flex items-center gap-2">
                    <TextInput
                        type="number"
                        step="0.001"
                        className="w-28"
                        value={value}
                        onChange={(e) => {
                            setValue(e.target.value);
                            setSaved(false);
                        }}
                    />
                    <SecondaryButton type="button" onClick={save}>
                        {saved ? 'Saved' : 'Save'}
                    </SecondaryButton>
                </div>
            </td>
        </tr>
    );
}

export default function InventoryCounts({
    counts,
    warehouses,
}: {
    counts: { data: InventoryCount[] };
    warehouses: Warehouse[];
}) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [openCountId, setOpenCountId] = useState<string | null>(null);

    const complete = (count: InventoryCount) => {
        askConfirm({
            title: `Complete count ${count.count_number}?`,
            message: 'Any variance adjusts stock immediately.',
            tone: 'warning',
            confirmLabel: 'Complete',
            onConfirm: () => {
                router.post(route('inventory.counts.complete', count.id));
            },
        });
    };

    return (
        <InventoryLayout title="Inventory Counts">
            <Card
                title="Inventory counts"
                description="Reconcile physical stock against the system. Completing a count with variances automatically adjusts inventory."
                actions={
                    <PrimaryButton onClick={() => setCreating(true)}>
                        Start count
                    </PrimaryButton>
                }
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {counts.data.map((count) => (
                        <div key={count.id} className="py-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                            {count.count_number}
                                        </p>
                                        <Badge
                                            variant={
                                                count.status === 'completed'
                                                    ? 'success'
                                                    : 'warning'
                                            }
                                        >
                                            {count.status}
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        {count.warehouse_name} &middot;{' '}
                                        {count.items?.length ?? 0} products
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    {count.status === 'in_progress' && (
                                        <>
                                            <SecondaryButton
                                                onClick={() =>
                                                    setOpenCountId(
                                                        openCountId === count.id
                                                            ? null
                                                            : count.id,
                                                    )
                                                }
                                            >
                                                {openCountId === count.id
                                                    ? 'Hide'
                                                    : 'Enter counts'}
                                            </SecondaryButton>
                                            <PrimaryButton
                                                onClick={() => complete(count)}
                                            >
                                                Complete
                                            </PrimaryButton>
                                        </>
                                    )}
                                </div>
                            </div>

                            {openCountId === count.id && (
                                <div className="mt-3 overflow-x-auto rounded-md border border-gray-100 p-3 dark:border-gray-700">
                                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr className="text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                                <th className="py-2 pr-4">
                                                    Product
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Expected
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Counted
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                            {count.items?.map((item) => (
                                                <CountItemRow
                                                    key={item.id}
                                                    item={item}
                                                />
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    ))}
                    {counts.data.length === 0 && (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No inventory counts yet.
                        </p>
                    )}
                </div>
            </Card>

            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="md"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Start inventory count
                    </h2>
                    <div className="mt-4">
                        <StartCountForm
                            warehouses={warehouses}
                            onSaved={() => setCreating(false)}
                        />
                    </div>
                </div>
            </Modal>
        </InventoryLayout>
    );
}
