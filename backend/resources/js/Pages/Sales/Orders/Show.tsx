import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import SalesLayout from '@/Layouts/SalesLayout';
import { formatCurrency } from '@/lib/currency';
import { Sale } from '@/types/sales';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const STATUS_VARIANT = {
    completed: 'success',
    voided: 'neutral',
    refunded: 'warning',
} as const;

const PAYMENT_STATUS_VARIANT = {
    paid: 'success',
    partial: 'warning',
    unpaid: 'danger',
} as const;

export default function SaleShow({ sale }: { sale: Sale }) {
    const [voiding, setVoiding] = useState(false);
    const [paying, setPaying] = useState(false);

    const voidForm = useForm({ reason: '' });
    const paymentForm = useForm({
        amount: '',
        payment_method: 'cash',
        reference_number: '',
    });

    const submitVoid = (e: FormEvent) => {
        e.preventDefault();
        voidForm.post(route('sales.orders.void', sale.id), {
            onSuccess: () => setVoiding(false),
        });
    };

    const submitPayment = (e: FormEvent) => {
        e.preventDefault();
        paymentForm.post(route('sales.orders.payments.store', sale.id), {
            onSuccess: () => {
                setPaying(false);
                paymentForm.reset();
            },
        });
    };

    return (
        <SalesLayout title={sale.sale_number}>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {sale.sale_number}
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {new Date(sale.created_at).toLocaleString()} ·{' '}
                        {sale.customer?.name ?? 'Walk-in customer'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant={STATUS_VARIANT[sale.status]}>
                        {sale.status}
                    </Badge>
                    <Badge
                        variant={PAYMENT_STATUS_VARIANT[sale.payment_status]}
                    >
                        {sale.payment_status}
                    </Badge>
                    {sale.status === 'completed' && (
                        <>
                            {sale.balance_due !== '0.00' && (
                                <PrimaryButton onClick={() => setPaying(true)}>
                                    Record Payment
                                </PrimaryButton>
                            )}
                            <Link
                                href={route(
                                    'sales.orders.returns.create',
                                    sale.id,
                                )}
                            >
                                <SecondaryButton type="button">
                                    Request Return
                                </SecondaryButton>
                            </Link>
                            <DangerButton onClick={() => setVoiding(true)}>
                                Void Sale
                            </DangerButton>
                        </>
                    )}
                </div>
            </div>

            {sale.status === 'voided' && sale.void_reason && (
                <div className="rounded-lg bg-gray-100 p-4 text-sm text-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                    Voided{' '}
                    {sale.voided_at &&
                        new Date(sale.voided_at).toLocaleString()}{' '}
                    — {sale.void_reason}
                </div>
            )}

            <Card title="Items">
                <table className="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                    <thead>
                        <tr>
                            {[
                                'Product',
                                'Qty',
                                'Unit Price',
                                'Discount',
                                'Tax',
                                'Total',
                            ].map((h) => (
                                <th
                                    key={h}
                                    className="py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {sale.items.map((item) => (
                            <tr key={item.id}>
                                <td className="py-2.5 text-sm text-gray-900 dark:text-gray-100">
                                    {item.product_name}
                                </td>
                                <td className="py-2.5 text-sm text-gray-700 dark:text-gray-300">
                                    {item.quantity}
                                </td>
                                <td className="py-2.5 text-sm text-gray-700 dark:text-gray-300">
                                    {formatCurrency(item.unit_price)}
                                </td>
                                <td className="py-2.5 text-sm text-gray-700 dark:text-gray-300">
                                    {formatCurrency(item.discount_amount)}
                                </td>
                                <td className="py-2.5 text-sm text-gray-700 dark:text-gray-300">
                                    {formatCurrency(item.tax_amount)}
                                </td>
                                <td className="py-2.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {formatCurrency(item.line_total)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="ml-auto mt-4 max-w-xs space-y-1.5 text-sm">
                    <div className="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Subtotal</span>
                        <span>{formatCurrency(sale.subtotal)}</span>
                    </div>
                    <div className="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Discount</span>
                        <span>-{formatCurrency(sale.discount_amount)}</span>
                    </div>
                    <div className="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Tax</span>
                        <span>{formatCurrency(sale.tax_amount)}</span>
                    </div>
                    <div className="flex justify-between border-t border-gray-100 pt-1.5 font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                        <span>Total</span>
                        <span>{formatCurrency(sale.total_amount)}</span>
                    </div>
                    <div className="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Paid</span>
                        <span>{formatCurrency(sale.paid_amount)}</span>
                    </div>
                    <div className="flex justify-between font-semibold text-amber-600 dark:text-amber-400">
                        <span>Balance Due</span>
                        <span>{formatCurrency(sale.balance_due)}</span>
                    </div>
                </div>
            </Card>

            <Card title="Payments">
                {sale.payments.length > 0 ? (
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {sale.payments.map((payment) => (
                            <div
                                key={payment.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <span className="text-gray-700 dark:text-gray-300">
                                    {payment.payment_method.replace('_', ' ')}{' '}
                                    {payment.reference_number &&
                                        `· ${payment.reference_number}`}
                                </span>
                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                    {formatCurrency(payment.amount)}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        No payments recorded.
                    </p>
                )}
            </Card>

            <Modal show={voiding} onClose={() => setVoiding(false)}>
                <form onSubmit={submitVoid} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Void this sale?
                    </h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        This will restore the stock that was deducted and
                        reverse any credit charged to the customer. This cannot
                        be undone.
                    </p>
                    <TextInput
                        className="mt-4 block w-full"
                        placeholder="Reason for voiding"
                        value={voidForm.data.reason}
                        onChange={(e) =>
                            voidForm.setData('reason', e.target.value)
                        }
                    />
                    <InputError
                        message={voidForm.errors.reason}
                        className="mt-2"
                    />
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setVoiding(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <DangerButton
                            type="submit"
                            disabled={voidForm.processing}
                        >
                            Void Sale
                        </DangerButton>
                    </div>
                </form>
            </Modal>

            <Modal show={paying} onClose={() => setPaying(false)}>
                <form onSubmit={submitPayment} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Record payment (balance:{' '}
                        {formatCurrency(sale.balance_due)})
                    </h2>
                    <div className="mt-4 grid gap-4">
                        <div>
                            <TextInput
                                type="number"
                                step="0.01"
                                className="block w-full"
                                placeholder="Amount"
                                value={paymentForm.data.amount}
                                onChange={(e) =>
                                    paymentForm.setData(
                                        'amount',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={paymentForm.errors.amount}
                                className="mt-2"
                            />
                        </div>
                        <SelectInput
                            value={paymentForm.data.payment_method}
                            onChange={(e) =>
                                paymentForm.setData(
                                    'payment_method',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </SelectInput>
                        <TextInput
                            placeholder="Reference number (optional)"
                            value={paymentForm.data.reference_number}
                            onChange={(e) =>
                                paymentForm.setData(
                                    'reference_number',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setPaying(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={paymentForm.processing}
                        >
                            Record Payment
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </SalesLayout>
    );
}
