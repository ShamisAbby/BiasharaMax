import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import SalesLayout from '@/Layouts/SalesLayout';
import { formatCurrency } from '@/lib/currency';
import { useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

interface SaleForReturn {
    id: string;
    sale_number: string;
    customer: { id: string; name: string } | null;
    items: Array<{
        id: string;
        product_name: string;
        quantity: string;
        unit_price: string;
    }>;
}

interface ReturnItemInput {
    sale_item_id: string;
    quantity_returned: string;
    condition: 'good' | 'damaged' | 'expired';
    restock: boolean;
    notes: string;
}

export default function SaleReturnForm({ sale }: { sale: SaleForReturn }) {
    const { data, setData, post, transform, processing, errors } = useForm({
        reason: 'changed_mind' as string,
        refund_method: 'cash' as string,
        notes: '',
        items: sale.items.map(
            (item): ReturnItemInput => ({
                sale_item_id: item.id,
                quantity_returned: '0',
                condition: 'good',
                restock: true,
                notes: '',
            }),
        ),
    });

    const updateItem = (
        index: number,
        field: keyof ReturnItemInput,
        value: string | boolean,
    ) => {
        const items = [...data.items];
        items[index] = { ...items[index], [field]: value };
        setData('items', items);
    };

    const estimatedRefund = useMemo(() => {
        return data.items.reduce((sum, item, index) => {
            const unitPrice = Number(sale.items[index]?.unit_price ?? 0);

            return sum + Number(item.quantity_returned || 0) * unitPrice;
        }, 0);
    }, [data.items, sale.items]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        transform((formData) => ({
            ...formData,
            items: formData.items.filter(
                (item) => Number(item.quantity_returned) > 0,
            ),
        }));
        post(route('sales.orders.returns.store', sale.id));
    };

    return (
        <SalesLayout title={`Return — ${sale.sale_number}`}>
            <form onSubmit={submit} className="space-y-6">
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Return against {sale.sale_number}
                    </h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {sale.customer?.name ?? 'Walk-in customer'}
                    </p>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Reason" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.reason}
                                onChange={(e) =>
                                    setData('reason', e.target.value)
                                }
                            >
                                <option value="changed_mind">
                                    Changed mind
                                </option>
                                <option value="damaged">Damaged</option>
                                <option value="wrong_item">Wrong item</option>
                                <option value="expired">Expired</option>
                                <option value="defective">Defective</option>
                                <option value="other">Other</option>
                            </SelectInput>
                            <InputError
                                message={errors.reason}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Refund method" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.refund_method}
                                onChange={(e) =>
                                    setData('refund_method', e.target.value)
                                }
                            >
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">
                                    Bank Transfer
                                </option>
                                <option value="mobile_money">
                                    Mobile Money
                                </option>
                                <option value="card">Card</option>
                                <option value="store_credit">
                                    Store Credit
                                </option>
                            </SelectInput>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Items to return
                    </h3>
                    <InputError message={errors.items} className="mt-2" />

                    <div className="mt-4 space-y-3">
                        {sale.items.map((item, index) => (
                            <div
                                key={item.id}
                                className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                            >
                                <div className="flex items-center justify-between">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {item.product_name}
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Sold: {item.quantity} @{' '}
                                        {formatCurrency(item.unit_price)}
                                    </p>
                                </div>
                                <div className="mt-3 grid gap-2 sm:grid-cols-4">
                                    <div>
                                        <InputLabel value="Quantity to return" />
                                        <TextInput
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            value={
                                                data.items[index]
                                                    .quantity_returned
                                            }
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'quantity_returned',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <InputLabel value="Condition" />
                                        <SelectInput
                                            value={data.items[index].condition}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'condition',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="good">Good</option>
                                            <option value="damaged">
                                                Damaged
                                            </option>
                                            <option value="expired">
                                                Expired
                                            </option>
                                        </SelectInput>
                                    </div>
                                    <div className="flex items-end pb-2">
                                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    data.items[index].restock
                                                }
                                                onChange={(e) =>
                                                    updateItem(
                                                        index,
                                                        'restock',
                                                        e.target.checked,
                                                    )
                                                }
                                                className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            Restock to inventory
                                        </label>
                                    </div>
                                </div>
                            </div>
                        ))}
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

                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Estimated refund:{' '}
                        <span className="font-semibold text-gray-900 dark:text-gray-100">
                            {formatCurrency(estimatedRefund)}
                        </span>
                    </p>
                    <PrimaryButton type="submit" disabled={processing}>
                        Submit Return Request
                    </PrimaryButton>
                </div>
            </form>
        </SalesLayout>
    );
}
