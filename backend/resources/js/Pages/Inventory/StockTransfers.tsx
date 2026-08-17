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
import { Warehouse } from '@/types';
import { StockTransfer } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface ProductOption {
    id: string;
    name: string;
    sku: string;
}

interface TransferItemInput {
    product_id: string;
    quantity: string;
    unit_cost: string;
}

function CreateTransferForm({
    warehouses,
    products,
    onSaved,
}: {
    warehouses: Warehouse[];
    products: ProductOption[];
    onSaved: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        from_warehouse_id: warehouses[0]?.id ?? '',
        to_warehouse_id: warehouses[1]?.id ?? warehouses[0]?.id ?? '',
        notes: '',
        items: [
            { product_id: products[0]?.id ?? '', quantity: '', unit_cost: '' },
        ] as TransferItemInput[],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { product_id: products[0]?.id ?? '', quantity: '', unit_cost: '' },
        ]);
    };

    const updateItem = (
        index: number,
        field: keyof TransferItemInput,
        value: string,
    ) => {
        const items = [...data.items];
        items[index] = { ...items[index], [field]: value };
        setData('items', items);
    };

    const removeItem = (index: number) => {
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('inventory.stock-transfers.store'), { onSuccess: onSaved });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel
                        htmlFor="transfer_from"
                        value="From warehouse"
                    />
                    <SelectInput
                        id="transfer_from"
                        className="mt-1 block w-full"
                        value={data.from_warehouse_id}
                        onChange={(e) =>
                            setData('from_warehouse_id', e.target.value)
                        }
                    >
                        {warehouses.map((warehouse) => (
                            <option key={warehouse.id} value={warehouse.id}>
                                {warehouse.name}
                            </option>
                        ))}
                    </SelectInput>
                    <InputError
                        message={errors.from_warehouse_id}
                        className="mt-2"
                    />
                </div>
                <div>
                    <InputLabel htmlFor="transfer_to" value="To warehouse" />
                    <SelectInput
                        id="transfer_to"
                        className="mt-1 block w-full"
                        value={data.to_warehouse_id}
                        onChange={(e) =>
                            setData('to_warehouse_id', e.target.value)
                        }
                    >
                        {warehouses.map((warehouse) => (
                            <option key={warehouse.id} value={warehouse.id}>
                                {warehouse.name}
                            </option>
                        ))}
                    </SelectInput>
                    <InputError
                        message={errors.to_warehouse_id}
                        className="mt-2"
                    />
                </div>
            </div>

            <div>
                <InputLabel value="Items" />
                <div className="mt-2 space-y-2">
                    {data.items.map((item, index) => (
                        <div key={index} className="grid gap-2 sm:grid-cols-4">
                            <SelectInput
                                value={item.product_id}
                                onChange={(e) =>
                                    updateItem(
                                        index,
                                        'product_id',
                                        e.target.value,
                                    )
                                }
                            >
                                {products.map((product) => (
                                    <option key={product.id} value={product.id}>
                                        {product.name} ({product.sku})
                                    </option>
                                ))}
                            </SelectInput>
                            <TextInput
                                type="number"
                                step="0.001"
                                placeholder="Quantity"
                                value={item.quantity}
                                onChange={(e) =>
                                    updateItem(
                                        index,
                                        'quantity',
                                        e.target.value,
                                    )
                                }
                            />
                            <TextInput
                                type="number"
                                step="0.01"
                                placeholder="Unit cost (optional)"
                                value={item.unit_cost}
                                onChange={(e) =>
                                    updateItem(
                                        index,
                                        'unit_cost',
                                        e.target.value,
                                    )
                                }
                            />
                            <SecondaryButton
                                type="button"
                                onClick={() => removeItem(index)}
                            >
                                Remove
                            </SecondaryButton>
                        </div>
                    ))}
                </div>
                <SecondaryButton
                    type="button"
                    className="mt-2"
                    onClick={addItem}
                >
                    Add item
                </SecondaryButton>
                <InputError message={errors.items} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="transfer_notes" value="Notes (optional)" />
                <TextInput
                    id="transfer_notes"
                    className="mt-1 block w-full"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>
                    Create transfer
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function StockTransfers({
    transfers,
    warehouses,
    products,
}: {
    transfers: { data: StockTransfer[] };
    warehouses: Warehouse[];
    products: ProductOption[];
}) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);

    const dispatch = (transfer: StockTransfer) => {
        askConfirm({
            title: `Dispatch transfer ${transfer.transfer_number}? Stock will leave the source warehouse.`,
            tone: 'warning',
            confirmLabel: 'Confirm',
            onConfirm: () => {
                router.post(
                    route('inventory.stock-transfers.dispatch', transfer.id),
                );
            },
        });
    };

    const receive = (transfer: StockTransfer) => {
        askConfirm({
            title: `Receive transfer ${transfer.transfer_number}? Stock will arrive at the destination warehouse.`,
            tone: 'warning',
            confirmLabel: 'Confirm',
            onConfirm: () => {
                router.post(
                    route('inventory.stock-transfers.receive', transfer.id),
                );
            },
        });
    };

    const cancel = (transfer: StockTransfer) => {
        askConfirm({
            title: `Cancel transfer ${transfer.transfer_number}?`,
            tone: 'danger',
            confirmLabel: 'Cancel',
            onConfirm: () => {
                router.post(
                    route('inventory.stock-transfers.cancel', transfer.id),
                );
            },
        });
    };

    const statusVariant = (status: StockTransfer['status']) => {
        if (status === 'completed') return 'success';
        if (status === 'cancelled') return 'danger';
        if (status === 'in_transit') return 'info';
        return 'warning';
    };

    return (
        <InventoryLayout title="Stock Transfers">
            <Card
                title="Stock transfers"
                description="Move stock between warehouses. Dispatching deducts source stock; receiving adds it to the destination."
                actions={
                    <PrimaryButton
                        onClick={() => setCreating(true)}
                        disabled={warehouses.length < 2}
                    >
                        New transfer
                    </PrimaryButton>
                }
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {transfers.data.map((transfer) => (
                        <div
                            key={transfer.id}
                            className="flex items-center justify-between py-3"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {transfer.transfer_number}
                                    </p>
                                    <Badge
                                        variant={statusVariant(transfer.status)}
                                    >
                                        {transfer.status}
                                    </Badge>
                                </div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {transfer.from_warehouse_name} &rarr;{' '}
                                    {transfer.to_warehouse_name} &middot;{' '}
                                    {transfer.items?.length ?? 0} item
                                    {transfer.items?.length === 1 ? '' : 's'}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                {transfer.status === 'pending' && (
                                    <>
                                        <SecondaryButton
                                            onClick={() => dispatch(transfer)}
                                        >
                                            Dispatch
                                        </SecondaryButton>
                                        <DangerButton
                                            onClick={() => cancel(transfer)}
                                        >
                                            Cancel
                                        </DangerButton>
                                    </>
                                )}
                                {transfer.status === 'in_transit' && (
                                    <SecondaryButton
                                        onClick={() => receive(transfer)}
                                    >
                                        Receive
                                    </SecondaryButton>
                                )}
                            </div>
                        </div>
                    ))}
                    {transfers.data.length === 0 && (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No stock transfers yet.
                        </p>
                    )}
                </div>
            </Card>

            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="2xl"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        New stock transfer
                    </h2>
                    <div className="mt-4">
                        <CreateTransferForm
                            warehouses={warehouses}
                            products={products}
                            onSaved={() => setCreating(false)}
                        />
                    </div>
                </div>
            </Modal>
        </InventoryLayout>
    );
}
