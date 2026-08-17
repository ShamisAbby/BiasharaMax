import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PurchasingLayout from '@/Layouts/PurchasingLayout';
import { formatCurrency } from '@/lib/currency';
import { PurchaseOrder } from '@/types/purchasing';
import { useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

type Option = { id: string; name: string };
type ProductOption = {
    id: string;
    name: string;
    sku: string | null;
    cost_price: string | null;
};

interface ItemInput {
    product_id: string;
    quantity_ordered: string;
    unit_cost: string;
    discount_amount: string;
    tax_amount: string;
    notes: string;
}

const emptyItem: ItemInput = {
    product_id: '',
    quantity_ordered: '1',
    unit_cost: '0',
    discount_amount: '0',
    tax_amount: '0',
    notes: '',
};

export default function PurchaseOrderForm({
    suppliers,
    branches,
    warehouses,
    products,
    order,
}: {
    suppliers: Option[];
    branches: Option[];
    warehouses: Option[];
    products: ProductOption[];
    order?: PurchaseOrder;
}) {
    const isEdit = !!order;

    const { data, setData, post, patch, processing, errors } = useForm({
        supplier_id: order?.supplier?.id ?? '',
        branch_id: order?.branch?.id ?? '',
        warehouse_id: order?.warehouse?.id ?? '',
        order_date: order?.order_date ?? new Date().toISOString().slice(0, 10),
        expected_delivery_date: order?.expected_delivery_date ?? '',
        items: (order?.items.map((item) => ({
            product_id: item.product_id,
            quantity_ordered: item.quantity_ordered,
            unit_cost: item.unit_cost,
            discount_amount: item.discount_amount,
            tax_amount: item.tax_amount,
            notes: item.notes ?? '',
        })) ?? [emptyItem]) as ItemInput[],
        discount_amount: order?.discount_amount ?? '0',
        shipping_cost: order?.shipping_cost ?? '0',
        other_charges: order?.other_charges ?? '0',
        notes: order?.notes ?? '',
        terms: order?.terms ?? '',
    });

    const addItem = () => setData('items', [...data.items, { ...emptyItem }]);

    const updateItem = (
        index: number,
        field: keyof ItemInput,
        value: string,
    ) => {
        const items = [...data.items];
        items[index] = { ...items[index], [field]: value };

        if (field === 'product_id') {
            const product = products.find((p) => p.id === value);
            if (product?.cost_price) {
                items[index].unit_cost = product.cost_price;
            }
        }

        setData('items', items);
    };

    const removeItem = (index: number) =>
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );

    const totals = useMemo(() => {
        const subtotal = data.items.reduce(
            (sum, item) =>
                sum +
                Number(item.quantity_ordered || 0) *
                    Number(item.unit_cost || 0),
            0,
        );
        const itemDiscount = data.items.reduce(
            (sum, item) => sum + Number(item.discount_amount || 0),
            0,
        );
        const itemTax = data.items.reduce(
            (sum, item) => sum + Number(item.tax_amount || 0),
            0,
        );
        const total =
            subtotal -
            itemDiscount -
            Number(data.discount_amount || 0) +
            itemTax +
            Number(data.shipping_cost || 0) +
            Number(data.other_charges || 0);

        return { subtotal, itemDiscount, itemTax, total };
    }, [
        data.items,
        data.discount_amount,
        data.shipping_cost,
        data.other_charges,
    ]);

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (isEdit && order) {
            patch(route('purchasing.orders.update', order.id));
        } else {
            post(route('purchasing.orders.store'));
        }
    };

    return (
        <PurchasingLayout
            title={isEdit ? `Edit ${order?.po_number}` : 'New Purchase Order'}
        >
            <form onSubmit={submit} className="space-y-6">
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Order details
                    </h3>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <InputLabel value="Supplier" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.supplier_id}
                                onChange={(e) =>
                                    setData('supplier_id', e.target.value)
                                }
                            >
                                <option value="">Select supplier</option>
                                {suppliers.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError
                                message={errors.supplier_id}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Branch" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.branch_id}
                                onChange={(e) =>
                                    setData('branch_id', e.target.value)
                                }
                            >
                                <option value="">No branch</option>
                                {branches.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.name}
                                    </option>
                                ))}
                            </SelectInput>
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
                                <option value="">No warehouse</option>
                                {warehouses.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Order date" />
                            <TextInput
                                type="date"
                                className="mt-1 block w-full"
                                value={data.order_date}
                                onChange={(e) =>
                                    setData('order_date', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.order_date}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Expected delivery date" />
                            <TextInput
                                type="date"
                                className="mt-1 block w-full"
                                value={data.expected_delivery_date}
                                onChange={(e) =>
                                    setData(
                                        'expected_delivery_date',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Items
                        </h3>
                        <SecondaryButton type="button" onClick={addItem}>
                            Add item
                        </SecondaryButton>
                    </div>
                    <InputError message={errors.items} className="mt-2" />

                    <div className="mt-4 space-y-3">
                        {data.items.map((item, index) => (
                            <div
                                key={index}
                                className="grid gap-2 sm:grid-cols-6"
                            >
                                <SelectInput
                                    className="sm:col-span-2"
                                    value={item.product_id}
                                    onChange={(e) =>
                                        updateItem(
                                            index,
                                            'product_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Select product</option>
                                    {products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                            {p.sku ? ` (${p.sku})` : ''}
                                        </option>
                                    ))}
                                </SelectInput>
                                <TextInput
                                    type="number"
                                    step="0.001"
                                    placeholder="Quantity"
                                    value={item.quantity_ordered}
                                    onChange={(e) =>
                                        updateItem(
                                            index,
                                            'quantity_ordered',
                                            e.target.value,
                                        )
                                    }
                                />
                                <TextInput
                                    type="number"
                                    step="0.01"
                                    placeholder="Unit cost"
                                    value={item.unit_cost}
                                    onChange={(e) =>
                                        updateItem(
                                            index,
                                            'unit_cost',
                                            e.target.value,
                                        )
                                    }
                                />
                                <TextInput
                                    type="number"
                                    step="0.01"
                                    placeholder="Discount"
                                    value={item.discount_amount}
                                    onChange={(e) =>
                                        updateItem(
                                            index,
                                            'discount_amount',
                                            e.target.value,
                                        )
                                    }
                                />
                                <div className="flex gap-2">
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        placeholder="Tax"
                                        value={item.tax_amount}
                                        onChange={(e) =>
                                            updateItem(
                                                index,
                                                'tax_amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => removeItem(index)}
                                    >
                                        &times;
                                    </SecondaryButton>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Notes & terms
                        </h3>
                        <div className="mt-4 space-y-3">
                            <textarea
                                placeholder="Notes"
                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                rows={3}
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                            />
                            <textarea
                                placeholder="Terms & conditions"
                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                rows={3}
                                value={data.terms}
                                onChange={(e) =>
                                    setData('terms', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Charges
                        </h3>
                        <div className="mt-4 grid grid-cols-3 gap-3">
                            <div>
                                <InputLabel value="Extra discount" />
                                <TextInput
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.discount_amount}
                                    onChange={(e) =>
                                        setData(
                                            'discount_amount',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <InputLabel value="Shipping" />
                                <TextInput
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.shipping_cost}
                                    onChange={(e) =>
                                        setData('shipping_cost', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <InputLabel value="Other charges" />
                                <TextInput
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.other_charges}
                                    onChange={(e) =>
                                        setData('other_charges', e.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <div className="mt-4 space-y-1 border-t border-gray-100 pt-4 text-sm dark:border-gray-700">
                            <div className="flex justify-between">
                                <span className="text-gray-500">Subtotal</span>
                                <span>{formatCurrency(totals.subtotal)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-500">Discount</span>
                                <span>
                                    -
                                    {formatCurrency(
                                        totals.itemDiscount +
                                            Number(data.discount_amount || 0),
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-500">Tax</span>
                                <span>{formatCurrency(totals.itemTax)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-500">
                                    Shipping + Other
                                </span>
                                <span>
                                    {formatCurrency(
                                        Number(data.shipping_cost || 0) +
                                            Number(data.other_charges || 0),
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-gray-100 pt-1 font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                                <span>Total</span>
                                <span>{formatCurrency(totals.total)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end gap-3">
                    <PrimaryButton type="submit" disabled={processing}>
                        {isEdit ? 'Save Changes' : 'Create Purchase Order'}
                    </PrimaryButton>
                </div>
            </form>
        </PurchasingLayout>
    );
}
