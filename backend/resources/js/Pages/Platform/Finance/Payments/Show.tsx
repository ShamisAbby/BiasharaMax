import BiBadge from '@/Components/Bi/BiBadge';
import BiCard from '@/Components/Bi/BiCard';
import PlatformFinanceLayout from '@/Layouts/PlatformFinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { Link } from '@inertiajs/react';

interface TimelineEntry {
    id: string;
    event: string;
    from_status: string | null;
    to_status: string | null;
    message: string | null;
    created_at: string;
}

interface TransactionDetail {
    id: string;
    type: string;
    reference_number: string;
    invoice_number: string | null;
    external_transaction_id: string | null;
    business: {
        id: string;
        name: string;
        owner_name: string | null;
        owner_email: string | null;
    } | null;
    gateway: { id: string; name: string } | null;
    amount: string;
    currency: string;
    tax_amount: string;
    discount_amount: string;
    fee_amount: string;
    commission_amount: string;
    refunded_amount: string;
    net_amount: string;
    status: string;
    payment_method: string | null;
    notes: string | null;
    is_refundable: boolean;
    paid_at: string | null;
    failed_at: string | null;
    refunded_at: string | null;
    timeline: TimelineEntry[];
    refunds: TransactionDetail[];
    created_at: string;
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    pending: 'warning',
    processing: 'info',
    successful: 'success',
    failed: 'danger',
    cancelled: 'neutral',
    refunded: 'neutral',
    partially_refunded: 'warning',
    expired: 'neutral',
};

export default function PaymentShow({
    transaction,
}: {
    transaction: TransactionDetail;
}) {
    return (
        <PlatformFinanceLayout title={transaction.reference_number}>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {transaction.reference_number}
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {transaction.invoice_number ?? transaction.type}
                        </p>
                    </div>
                    <BiBadge
                        variant={
                            STATUS_VARIANT[transaction.status] ?? 'neutral'
                        }
                    >
                        {transaction.status}
                    </BiBadge>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <BiCard title="Transaction" className="lg:col-span-2">
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">
                                    Business
                                </dt>
                                <dd className="font-medium text-gray-900 dark:text-gray-100">
                                    {transaction.business?.name ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">
                                    Owner
                                </dt>
                                <dd className="font-medium text-gray-900 dark:text-gray-100">
                                    {transaction.business?.owner_name} (
                                    {transaction.business?.owner_email})
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">
                                    Gateway
                                </dt>
                                <dd className="font-medium text-gray-900 dark:text-gray-100">
                                    {transaction.gateway?.name ?? 'Manual'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">
                                    Payment method
                                </dt>
                                <dd className="font-medium text-gray-900 dark:text-gray-100">
                                    {transaction.payment_method ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">
                                    External transaction ID
                                </dt>
                                <dd className="font-medium text-gray-900 dark:text-gray-100">
                                    {transaction.external_transaction_id ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500 dark:text-gray-400">
                                    Created
                                </dt>
                                <dd className="font-medium text-gray-900 dark:text-gray-100">
                                    {new Date(
                                        transaction.created_at,
                                    ).toLocaleString()}
                                </dd>
                            </div>
                        </dl>

                        {transaction.notes && (
                            <p className="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                {transaction.notes}
                            </p>
                        )}
                    </BiCard>

                    <BiCard title="Amounts">
                        <dl className="space-y-2 text-sm">
                            <Row
                                label="Gross amount"
                                value={formatCurrency(
                                    Number(transaction.amount),
                                )}
                            />
                            <Row
                                label="Tax"
                                value={formatCurrency(
                                    Number(transaction.tax_amount),
                                )}
                            />
                            <Row
                                label="Discount"
                                value={`-${formatCurrency(Number(transaction.discount_amount))}`}
                            />
                            <Row
                                label="Gateway fee"
                                value={`-${formatCurrency(Number(transaction.fee_amount))}`}
                            />
                            <Row
                                label="Platform commission"
                                value={`-${formatCurrency(Number(transaction.commission_amount))}`}
                            />
                            <Row
                                label="Refunded"
                                value={formatCurrency(
                                    Number(transaction.refunded_amount),
                                )}
                            />
                            <div className="border-t border-gray-100 pt-2 dark:border-gray-700">
                                <Row
                                    label="Net amount"
                                    value={formatCurrency(
                                        Number(transaction.net_amount),
                                    )}
                                    bold
                                />
                            </div>
                        </dl>
                    </BiCard>
                </div>

                {transaction.refunds.length > 0 && (
                    <BiCard title="Refunds">
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {transaction.refunds.map((refund) => (
                                <div
                                    key={refund.id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <Link
                                        href={route(
                                            'platform.finance.payments.show',
                                            refund.id,
                                        )}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        {refund.reference_number}
                                    </Link>
                                    <span>
                                        {formatCurrency(Number(refund.amount))}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </BiCard>
                )}

                <BiCard title="Payment Timeline">
                    {transaction.timeline.length > 0 ? (
                        <ol className="space-y-4 border-l border-gray-200 pl-4 dark:border-gray-700">
                            {transaction.timeline.map((entry) => (
                                <li key={entry.id} className="relative">
                                    <span className="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-indigo-500" />
                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {entry.event.replace(/_/g, ' ')}
                                    </p>
                                    {entry.message && (
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            {entry.message}
                                        </p>
                                    )}
                                    <p className="text-xs text-gray-400 dark:text-gray-500">
                                        {new Date(
                                            entry.created_at,
                                        ).toLocaleString()}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No timeline events yet.
                        </p>
                    )}
                </BiCard>
            </div>
        </PlatformFinanceLayout>
    );
}

function Row({
    label,
    value,
    bold,
}: {
    label: string;
    value: string;
    bold?: boolean;
}) {
    return (
        <div className="flex items-center justify-between">
            <dt className="text-gray-500 dark:text-gray-400">{label}</dt>
            <dd
                className={
                    bold
                        ? 'font-semibold text-gray-900 dark:text-gray-100'
                        : 'text-gray-700 dark:text-gray-300'
                }
            >
                {value}
            </dd>
        </div>
    );
}
