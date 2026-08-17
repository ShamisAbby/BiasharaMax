import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import { useConfirm } from '@/Components/ConfirmDialog';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import { PurchaseOrder } from '@/types/purchasing';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const STATUS_VARIANT: Record<
    string,
    'neutral' | 'success' | 'warning' | 'danger' | 'info'
> = {
    draft: 'neutral',
    pending_approval: 'warning',
    approved: 'info',
    rejected: 'danger',
    sent: 'info',
    partially_received: 'warning',
    fully_received: 'success',
    cancelled: 'danger',
    closed: 'neutral',
};

const PAYMENT_STATUS_VARIANT = {
    paid: 'success',
    partial: 'warning',
    unpaid: 'danger',
} as const;

export default function PurchaseOrderShow({ order }: { order: PurchaseOrder }) {
    const askConfirm = useConfirm();
    const [rejecting, setRejecting] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [paying, setPaying] = useState(false);

    const rejectForm = useForm({ rejection_reason: '' });
    const cancelForm = useForm({ cancellation_reason: '' });
    const paymentForm = useForm({
        amount: '',
        payment_method: 'cash',
        reference_number: '',
    });

    const submitForApproval = () =>
        router.post(route('purchasing.orders.submit', order.id));
    const approve = () =>
        router.post(route('purchasing.orders.approve', order.id));
    const send = () => router.post(route('purchasing.orders.send', order.id));
    const close = () => router.post(route('purchasing.orders.close', order.id));
    const duplicate = () =>
        router.post(route('purchasing.orders.duplicate', order.id));

    const destroy = () => {
        askConfirm({
            title: `Delete purchase order ${order.po_number}?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('purchasing.orders.destroy', order.id));
            },
        });
    };

    const submitReject = (e: FormEvent) => {
        e.preventDefault();
        rejectForm.post(route('purchasing.orders.reject', order.id), {
            onSuccess: () => setRejecting(false),
        });
    };

    const submitCancel = (e: FormEvent) => {
        e.preventDefault();
        cancelForm.post(route('purchasing.orders.cancel', order.id), {
            onSuccess: () => setCancelling(false),
        });
    };

    const submitPayment = (e: FormEvent) => {
        e.preventDefault();
        paymentForm.post(route('purchasing.orders.payments.store', order.id), {
            onSuccess: () => {
                setPaying(false);
                paymentForm.reset();
            },
        });
    };

    const canRecordPayment =
        order.status !== 'cancelled' && order.balance_due !== '0.00';
    const canEdit = order.status === 'draft';
    const canSubmit = order.status === 'draft';
    const canApprove = order.status === 'pending_approval';
    const canSend = order.status === 'approved';
    const canReceive = ['sent', 'partially_received', 'approved'].includes(
        order.status,
    );
    const canCancel = [
        'draft',
        'pending_approval',
        'approved',
        'sent',
        'partially_received',
    ].includes(order.status);
    const canClose = order.status === 'fully_received';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {order.po_number}
                        </h2>
                        <div className="mt-1 flex items-center gap-2">
                            <Badge variant={STATUS_VARIANT[order.status]}>
                                {order.status.replace('_', ' ')}
                            </Badge>
                            <Badge
                                variant={
                                    PAYMENT_STATUS_VARIANT[order.payment_status]
                                }
                            >
                                {order.payment_status}
                            </Badge>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit && (
                            <Link
                                href={route('purchasing.orders.edit', order.id)}
                            >
                                <SecondaryButton type="button">
                                    Edit
                                </SecondaryButton>
                            </Link>
                        )}
                        <SecondaryButton type="button" onClick={duplicate}>
                            Duplicate
                        </SecondaryButton>
                        {canSubmit && (
                            <PrimaryButton
                                type="button"
                                onClick={submitForApproval}
                            >
                                Submit for Approval
                            </PrimaryButton>
                        )}
                        {canApprove && (
                            <>
                                <PrimaryButton type="button" onClick={approve}>
                                    Approve
                                </PrimaryButton>
                                <DangerButton
                                    type="button"
                                    onClick={() => setRejecting(true)}
                                >
                                    Reject
                                </DangerButton>
                            </>
                        )}
                        {canSend && (
                            <PrimaryButton type="button" onClick={send}>
                                Send to Supplier
                            </PrimaryButton>
                        )}
                        {canReceive && (
                            <Link
                                href={route(
                                    'purchasing.goods-received.create',
                                    order.id,
                                )}
                            >
                                <PrimaryButton type="button">
                                    Receive Goods
                                </PrimaryButton>
                            </Link>
                        )}
                        {canClose && (
                            <PrimaryButton type="button" onClick={close}>
                                Close Order
                            </PrimaryButton>
                        )}
                        {canRecordPayment && (
                            <PrimaryButton
                                type="button"
                                onClick={() => setPaying(true)}
                            >
                                Record Payment
                            </PrimaryButton>
                        )}
                        {canCancel && (
                            <DangerButton
                                type="button"
                                onClick={() => setCancelling(true)}
                            >
                                Cancel
                            </DangerButton>
                        )}
                        {canEdit && (
                            <DangerButton type="button" onClick={destroy}>
                                Delete
                            </DangerButton>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`Purchasing — ${order.po_number}`} />

            <div className="py-8">
                <div className="mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
                    {order.rejection_reason && (
                        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
                            <strong>Rejected:</strong> {order.rejection_reason}
                        </div>
                    )}
                    {order.cancellation_reason && (
                        <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                            <strong>Cancelled:</strong>{' '}
                            {order.cancellation_reason}
                        </div>
                    )}

                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <Card title="Supplier">
                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                {order.supplier?.name ?? '—'}
                            </p>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {order.supplier?.email ??
                                    order.supplier?.phone ??
                                    ''}
                            </p>
                        </Card>
                        <Card title="Order Date">
                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                {order.order_date}
                            </p>
                            {order.expected_delivery_date && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Expected: {order.expected_delivery_date}
                                </p>
                            )}
                        </Card>
                        <Card title="Branch / Warehouse">
                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                {order.branch?.name ?? '—'}
                            </p>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {order.warehouse?.name ?? '—'}
                            </p>
                        </Card>
                        <Card title="Total Amount">
                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                {formatCurrency(order.total_amount)}
                            </p>
                            <div className="mt-2 space-y-0.5 text-sm">
                                <div className="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span>Paid</span>
                                    <span>
                                        {formatCurrency(order.paid_amount)}
                                    </span>
                                </div>
                                <div className="flex justify-between font-semibold text-amber-600 dark:text-amber-400">
                                    <span>Balance Due</span>
                                    <span>
                                        {formatCurrency(order.balance_due)}
                                    </span>
                                </div>
                            </div>
                        </Card>
                    </div>

                    <Card title="Items">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th className="pb-2 font-medium">
                                        Product
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Ordered
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Received
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Remaining
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Unit Cost
                                    </th>
                                    <th className="pb-2 font-medium">
                                        Line Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {order.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-2.5 text-gray-900 dark:text-gray-100">
                                            {item.product_name}
                                        </td>
                                        <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                            {item.quantity_ordered}
                                        </td>
                                        <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                            {item.quantity_received}
                                        </td>
                                        <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                            {item.remaining_quantity}
                                        </td>
                                        <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                            {formatCurrency(item.unit_cost)}
                                        </td>
                                        <td className="py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                            {formatCurrency(item.line_total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Card>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card title="Notes">
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {order.notes || 'No notes.'}
                            </p>
                        </Card>
                        <Card title="Terms & Conditions">
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {order.terms || 'No terms specified.'}
                            </p>
                        </Card>
                    </div>

                    <Card title="Payments">
                        {order.payments.length > 0 ? (
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {order.payments.map((payment) => (
                                    <div
                                        key={payment.id}
                                        className="flex items-center justify-between py-2 text-sm"
                                    >
                                        <span className="text-gray-700 dark:text-gray-300">
                                            {payment.payment_method.replace(
                                                '_',
                                                ' ',
                                            )}{' '}
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
                </div>
            </div>

            <Modal show={rejecting} onClose={() => setRejecting(false)}>
                <form onSubmit={submitReject} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Reject purchase order
                    </h2>
                    <textarea
                        className="mt-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        rows={3}
                        placeholder="Reason for rejection"
                        value={rejectForm.data.rejection_reason}
                        onChange={(e) =>
                            rejectForm.setData(
                                'rejection_reason',
                                e.target.value,
                            )
                        }
                    />
                    <InputError
                        message={rejectForm.errors.rejection_reason}
                        className="mt-2"
                    />
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setRejecting(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={rejectForm.processing}
                        >
                            Reject
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal show={cancelling} onClose={() => setCancelling(false)}>
                <form onSubmit={submitCancel} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Cancel purchase order
                    </h2>
                    <textarea
                        className="mt-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        rows={3}
                        placeholder="Reason for cancellation"
                        value={cancelForm.data.cancellation_reason}
                        onChange={(e) =>
                            cancelForm.setData(
                                'cancellation_reason',
                                e.target.value,
                            )
                        }
                    />
                    <InputError
                        message={cancelForm.errors.cancellation_reason}
                        className="mt-2"
                    />
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setCancelling(false)}
                        >
                            Back
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={cancelForm.processing}
                        >
                            Confirm Cancellation
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal show={paying} onClose={() => setPaying(false)}>
                <form onSubmit={submitPayment} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Record payment (balance:{' '}
                        {formatCurrency(order.balance_due)})
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
        </AuthenticatedLayout>
    );
}
