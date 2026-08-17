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
import { Branch, Warehouse } from '@/types';
import { StockAdjustment } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface ProductOption {
    id: string;
    name: string;
    sku: string;
    has_expiry: boolean;
    has_batch: boolean;
}

interface AdjustmentItemInput {
    product_id: string;
    direction: 'in' | 'out';
    quantity: string;
    unit_cost: string;
    batch_number: string;
    manufactured_date: string;
    expiry_date: string;
}

function CreateAdjustmentForm({
    branches,
    warehouses,
    products,
    onSaved,
}: {
    branches: Branch[];
    warehouses: Warehouse[];
    products: ProductOption[];
    onSaved: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        branch_id: branches[0]?.id ?? '',
        warehouse_id: warehouses[0]?.id ?? '',
        reason: 'correction',
        notes: '',
        items: [
            {
                product_id: products[0]?.id ?? '',
                direction: 'out' as 'in' | 'out',
                quantity: '',
                unit_cost: '',
                batch_number: '',
                manufactured_date: '',
                expiry_date: '',
            },
        ] as AdjustmentItemInput[],
    });

    const blankItem = (): AdjustmentItemInput => ({
        product_id: products[0]?.id ?? '',
        direction: 'out',
        quantity: '',
        unit_cost: '',
        batch_number: '',
        manufactured_date: '',
        expiry_date: '',
    });

    const addItem = () => {
        setData('items', [...data.items, blankItem()]);
    };

    const updateItem = (
        index: number,
        field: keyof AdjustmentItemInput,
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
        post(route('inventory.stock-adjustments.store'), {
            onSuccess: onSaved,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="adj_branch" value="Branch" />
                    <SelectInput
                        id="adj_branch"
                        className="mt-1 block w-full"
                        value={data.branch_id}
                        onChange={(e) => setData('branch_id', e.target.value)}
                    >
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </SelectInput>
                </div>
                <div>
                    <InputLabel htmlFor="adj_warehouse" value="Warehouse" />
                    <SelectInput
                        id="adj_warehouse"
                        className="mt-1 block w-full"
                        value={data.warehouse_id}
                        onChange={(e) =>
                            setData('warehouse_id', e.target.value)
                        }
                    >
                        {warehouses.map((warehouse) => (
                            <option key={warehouse.id} value={warehouse.id}>
                                {warehouse.name}
                            </option>
                        ))}
                    </SelectInput>
                </div>
            </div>

            <div>
                <InputLabel htmlFor="adj_reason" value="Reason" />
                <SelectInput
                    id="adj_reason"
                    className="mt-1 block w-full"
                    value={data.reason}
                    onChange={(e) => setData('reason', e.target.value)}
                >
                    <option value="damage">Damage</option>
                    <option value="theft">Theft</option>
                    <option value="expiry">Expiry</option>
                    <option value="correction">Correction</option>
                    <option value="count">Count</option>
                    <option value="other">Other</option>
                </SelectInput>
            </div>

            <div>
                <InputLabel value="Items" />
                <div className="mt-2 space-y-3">
                    {data.items.map((item, index) => {
                        const selectedProduct = products.find(
                            (p) => p.id === item.product_id,
                        );
                        const showBatch =
                            item.direction === 'in' &&
                            (selectedProduct?.has_batch ||
                                selectedProduct?.has_expiry);

                        return (
                            <div
                                key={index}
                                className="rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                            >
                                <div className="grid gap-2 sm:grid-cols-5">
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
                                            <option
                                                key={product.id}
                                                value={product.id}
                                            >
                                                {product.name} ({product.sku})
                                            </option>
                                        ))}
                                    </SelectInput>
                                    <SelectInput
                                        value={item.direction}
                                        onChange={(e) =>
                                            updateItem(
                                                index,
                                                'direction',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="out">Decrease</option>
                                        <option value="in">Increase</option>
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

                                {/* Batch / expiry fields — only for stock-in on tracked products */}
                                {showBatch && (
                                    <div className="mt-2 grid gap-2 sm:grid-cols-3">
                                        {selectedProduct?.has_batch && (
                                            <div>
                                                <InputLabel value="Batch number" />
                                                <TextInput
                                                    className="mt-1 block w-full"
                                                    placeholder="e.g. LOT-2026-001"
                                                    value={item.batch_number}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'batch_number',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        )}
                                        {selectedProduct?.has_expiry && (
                                            <>
                                                <div>
                                                    <InputLabel value="Manufacture date" />
                                                    <TextInput
                                                        type="date"
                                                        className="mt-1 block w-full"
                                                        value={
                                                            item.manufactured_date
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
                                                    <InputLabel value="Expiry date" />
                                                    <TextInput
                                                        type="date"
                                                        className="mt-1 block w-full"
                                                        value={item.expiry_date}
                                                        onChange={(e) =>
                                                            updateItem(
                                                                index,
                                                                'expiry_date',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
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
                <InputLabel htmlFor="adj_notes" value="Notes (optional)" />
                <TextInput
                    id="adj_notes"
                    className="mt-1 block w-full"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>
                    Save as draft
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function StockAdjustments({
    adjustments,
    branches,
    warehouses,
    products,
}: {
    adjustments: { data: StockAdjustment[] };
    branches: Branch[];
    warehouses: Warehouse[];
    products: ProductOption[];
}) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);

    const complete = (adjustment: StockAdjustment) => {
        askConfirm({
            title: `Complete adjustment ${adjustment.adjustment_number}?`,
            message: 'Stock levels are updated immediately.',
            tone: 'warning',
            confirmLabel: 'Complete',
            onConfirm: () => {
                router.post(
                    route(
                        'inventory.stock-adjustments.complete',
                        adjustment.id,
                    ),
                );
            },
        });
    };

    const destroy = (adjustment: StockAdjustment) => {
        askConfirm({
            title: `Delete draft adjustment ${adjustment.adjustment_number}?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('inventory.stock-adjustments.destroy', adjustment.id),
                );
            },
        });
    };

    return (
        <InventoryLayout title="Stock Adjustments">
            <Card
                title="Stock adjustments"
                description="Record damage, theft, expiry and manual corrections. Drafts have no effect until completed."
                actions={
                    <PrimaryButton onClick={() => setCreating(true)}>
                        New adjustment
                    </PrimaryButton>
                }
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {adjustments.data.map((adjustment) => (
                        <div
                            key={adjustment.id}
                            className="flex items-center justify-between py-3"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {adjustment.adjustment_number}
                                    </p>
                                    <Badge variant="neutral">
                                        {adjustment.reason}
                                    </Badge>
                                    <Badge
                                        variant={
                                            adjustment.status === 'completed'
                                                ? 'success'
                                                : 'warning'
                                        }
                                    >
                                        {adjustment.status}
                                    </Badge>
                                </div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {adjustment.warehouse_name} &middot;{' '}
                                    {adjustment.items?.length ?? 0} item
                                    {adjustment.items?.length === 1 ? '' : 's'}
                                </p>
                            </div>
                            {adjustment.status === 'draft' && (
                                <div className="flex gap-2">
                                    <SecondaryButton
                                        onClick={() => complete(adjustment)}
                                    >
                                        Complete
                                    </SecondaryButton>
                                    <DangerButton
                                        onClick={() => destroy(adjustment)}
                                    >
                                        Delete
                                    </DangerButton>
                                </div>
                            )}
                        </div>
                    ))}
                    {adjustments.data.length === 0 && (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No stock adjustments yet.
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
                        New stock adjustment
                    </h2>
                    <div className="mt-4">
                        <CreateAdjustmentForm
                            branches={branches}
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
