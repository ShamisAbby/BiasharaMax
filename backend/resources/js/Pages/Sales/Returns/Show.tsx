import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SalesLayout from '@/Layouts/SalesLayout';
import { formatCurrency } from '@/lib/currency';
import { SaleReturn, SaleReturnStatus } from '@/types/sales';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const STATUS_VARIANT: Record<
    SaleReturnStatus,
    'warning' | 'success' | 'danger'
> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
};

export default function SaleReturnShow({
    returnRecord,
}: {
    returnRecord: SaleReturn;
}) {
    const [rejecting, setRejecting] = useState(false);
    const rejectForm = useForm({ rejection_reason: '' });

    const approve = () =>
        router.post(route('sales.returns.approve', returnRecord.id));

    const submitReject = (e: FormEvent) => {
        e.preventDefault();
        rejectForm.post(route('sales.returns.reject', returnRecord.id), {
            onSuccess: () => setRejecting(false),
        });
    };

    return (
        <SalesLayout title={returnRecord.return_number}>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {returnRecord.return_number}
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {returnRecord.sale && (
                            <>
                                Against{' '}
                                <Link
                                    href={route(
                                        'sales.orders.show',
                                        returnRecord.sale.id,
                                    )}
                                    className="text-indigo-600 hover:underline"
                                >
                                    {returnRecord.sale.sale_number}
                                </Link>
                                {' · '}
                            </>
                        )}
                        {returnRecord.customer?.name ?? 'Walk-in customer'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant={STATUS_VARIANT[returnRecord.status]}>
                        {returnRecord.status}
                    </Badge>
                    {returnRecord.status === 'pending' && (
                        <>
                            <PrimaryButton onClick={approve}>
                                Approve
                            </PrimaryButton>
                            <DangerButton onClick={() => setRejecting(true)}>
                                Reject
                            </DangerButton>
                        </>
                    )}
                </div>
            </div>

            {returnRecord.rejection_reason && (
                <div className="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">
                    <strong>Rejected:</strong> {returnRecord.rejection_reason}
                </div>
            )}

            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card title="Reason">
                    <p className="font-medium capitalize text-gray-900 dark:text-gray-100">
                        {returnRecord.reason.replace('_', ' ')}
                    </p>
                </Card>
                <Card title="Refund Method">
                    <p className="font-medium capitalize text-gray-900 dark:text-gray-100">
                        {returnRecord.refund_method?.replace('_', ' ') ?? '—'}
                    </p>
                </Card>
                <Card title="Refund Amount">
                    <p className="text-xl font-bold text-gray-900 dark:text-gray-100">
                        {formatCurrency(returnRecord.refund_amount)}
                    </p>
                </Card>
            </div>

            <Card title="Items">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-gray-400">
                            <th className="pb-2 font-medium">Product</th>
                            <th className="pb-2 font-medium">Qty Returned</th>
                            <th className="pb-2 font-medium">Condition</th>
                            <th className="pb-2 font-medium">Restocked</th>
                            <th className="pb-2 font-medium">Refund</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {returnRecord.items.map((item) => (
                            <tr key={item.id}>
                                <td className="py-2.5 text-gray-900 dark:text-gray-100">
                                    {item.product?.name ?? '—'}
                                </td>
                                <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                    {item.quantity_returned}
                                </td>
                                <td className="py-2.5 capitalize text-gray-700 dark:text-gray-300">
                                    {item.condition}
                                </td>
                                <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                    {item.restock ? 'Yes' : 'No'}
                                </td>
                                <td className="py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                    {formatCurrency(item.line_refund_amount)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>

            {returnRecord.notes && (
                <Card title="Notes">
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        {returnRecord.notes}
                    </p>
                </Card>
            )}

            <Modal show={rejecting} onClose={() => setRejecting(false)}>
                <form onSubmit={submitReject} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Reject this return
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
        </SalesLayout>
    );
}
