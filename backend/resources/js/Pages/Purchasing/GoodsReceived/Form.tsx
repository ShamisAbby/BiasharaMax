import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PurchasingLayout from '@/Layouts/PurchasingLayout';
import { PurchaseOrder } from '@/types/purchasing';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { id: string; name: string };

interface ReceiveItemInput {
    purchase_order_item_id: string;
    quantity_received: string;
    quantity_damaged: string;
    quantity_rejected: string;
    batch_number: string;
    manufactured_date: string;
    expiry_date: string;
    notes: string;
}

export default function GoodsReceivedForm({
    order,
    branches,
    warehouses,
}: {
    order: PurchaseOrder;
    branches: Option[];
    warehouses: Option[];
}) {
    const receivableItems = order.items.filter(
        (item) => Number(item.remaining_quantity) > 0,
    );

    const { data, setData, post, processing, errors } = useForm({
        branch_id: order.branch?.id ?? branches[0]?.id ?? '',
        warehouse_id: order.warehouse?.id ?? warehouses[0]?.id ?? '',
        received_at: new Date().toISOString().slice(0, 16),
        notes: '',
        items: receivableItems.map(
            (item): ReceiveItemInput => ({
                purchase_order_item_id: item.id,
                quantity_received: item.remaining_quantity,
                quantity_damaged: '0',
                quantity_rejected: '0',
                batch_number: '',
                manufactured_date: '',
                expiry_date: '',
                notes: '',
            }),
        ),
    });

    const updateItem = (
        index: number,
        field: keyof ReceiveItemInput,
        value: string,
    ) => {
        const items = [...data.items];
        items[index] = { ...items[index], [field]: value };
        setData('items', items);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('purchasing.goods-received.store', order.id));
    };

    return (
        <PurchasingLayout title={`Receive — ${order.po_number}`}>
            <form onSubmit={submit} className="space-y-6">
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Receiving details
                    </h3>
                    <div className="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel value="Branch" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.branch_id}
                                onChange={(e) =>
                                    setData('branch_id', e.target.value)
                                }
                            >
                                <option value="">Select branch</option>
                                {branches.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.name}
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError
                                message={errors.branch_id}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Warehouse" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.warehouse_id}
                                onChange={(e) =>
                                    setData('warehouse_id', e.target.value)
                                }
                            >
                                <option value="">Select warehouse</option>
                                {warehouses.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError
                                message={errors.warehouse_id}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Received at" />
                            <TextInput
                                type="datetime-local"
                                className="mt-1 block w-full"
                                value={data.received_at}
                                onChange={(e) =>
                                    setData('received_at', e.target.value)
                                }
                            />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Items
                    </h3>
                    <InputError message={errors.items} className="mt-2" />

                    <div className="mt-4 space-y-4">
                        {receivableItems.map((item, index) => (
                            <div
                                key={item.id}
                                className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                            >
                                <div className="flex items-center justify-between">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {item.product_name}
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Remaining: {item.remaining_quantity}
                                    </p>
                                </div>
                                <div className="mt-3 grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                                    <div>
                                        <InputLabel value="Received" />
                                        <TextInput
                                            type="number"
                                            step="0.001"
                                            value={
                                                data.items[index]
                                                    .quantity_received
                                            }
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'quantity_received',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <InputLabel value="Damaged" />
                                        <TextInput
                                            type="number"
                                            step="0.001"
                                            value={
                                                data.items[index]
                                                    .quantity_damaged
                                            }
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'quantity_damaged',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <InputLabel value="Rejected" />
                                        <TextInput
                                            type="number"
                                            step="0.001"
                                            value={
                                                data.items[index]
                                                    .quantity_rejected
                                            }
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'quantity_rejected',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <InputLabel value="Batch number" />
                                        <TextInput
                                            value={
                                                data.items[index].batch_number
                                            }
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'batch_number',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <InputLabel value="Manufactured" />
                                        <TextInput
                                            type="date"
                                            value={
                                                data.items[index]
                                                    .manufactured_date
                                            }
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'manufactured_date',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <InputLabel value="Expiry" />
                                        <TextInput
                                            type="date"
                                            value={
                                                data.items[index].expiry_date
                                            }
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'expiry_date',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}

                        {receivableItems.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Every line on this order has already been fully
                                received.
                            </p>
                        )}
                    </div>
                </div>

                <div>
                    <InputLabel value="Notes" />
                    <textarea
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        rows={2}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                </div>

                <div className="flex justify-end">
                    <PrimaryButton
                        type="submit"
                        disabled={processing || receivableItems.length === 0}
                    >
                        Record Delivery
                    </PrimaryButton>
                </div>
            </form>
        </PurchasingLayout>
    );
}
