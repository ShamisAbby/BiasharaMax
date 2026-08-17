import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformFinanceLayout from '@/Layouts/PlatformFinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, FormEventHandler, useState } from 'react';

interface TransactionRow {
    id: string;
    type: string;
    reference_number: string;
    invoice_number: string | null;
    business: { id: string; name: string; owner_name: string | null } | null;
    gateway: { id: string; name: string } | null;
    amount: string;
    currency: string;
    status: string;
    payment_method: string | null;
    is_refundable: boolean;
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

const EMPTY_MANUAL_FORM = {
    business_id: '',
    type: 'manual',
    amount: '',
    currency: 'TZS',
    tax_amount: '0',
    discount_amount: '0',
    fee_amount: '0',
    commission_amount: '0',
    payment_method: 'cash',
    invoice_number: '',
    notes: '',
};

export default function PaymentsIndex({
    transactions,
    filters,
}: {
    transactions: {
        data: TransactionRow[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    filters: Record<string, string>;
}) {
    const { notify } = useBiNotification();
    const [search, setSearch] = useState(filters.search ?? '');
    const [recording, setRecording] = useState(false);
    const [refunding, setRefunding] = useState<TransactionRow | null>(null);
    const [refundAmount, setRefundAmount] = useState('');
    const [refundReason, setRefundReason] = useState('');

    const { data, setData, post, processing, errors, reset } =
        useForm(EMPTY_MANUAL_FORM);

    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('platform.finance.payments.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const onSearchSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const submitManual: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('platform.finance.payments.store'), {
            onSuccess: () => {
                setRecording(false);
                reset();
                notify('Payment recorded.', 'success');
            },
        });
    };

    const retry = (transaction: TransactionRow) => {
        router.post(
            route('platform.finance.payments.retry', transaction.id),
            {},
            {
                onSuccess: () => notify('Payment retried.', 'success'),
                onError: (errs) =>
                    errs.transaction && notify(errs.transaction, 'error'),
            },
        );
    };

    const approve = (transaction: TransactionRow) => {
        router.post(
            route('platform.finance.payments.approve', transaction.id),
            {},
            {
                onSuccess: () => notify('Payment approved.', 'success'),
            },
        );
    };

    const openRefund = (transaction: TransactionRow) => {
        setRefundAmount(transaction.amount);
        setRefundReason('');
        setRefunding(transaction);
    };

    const submitRefund = (e: FormEvent) => {
        e.preventDefault();
        if (!refunding) return;

        router.post(
            route('platform.finance.payments.refund', refunding.id),
            { amount: refundAmount, reason: refundReason },
            {
                onSuccess: () => {
                    setRefunding(null);
                    notify('Payment refunded.', 'success');
                },
                onError: (errs) =>
                    errs.transaction && notify(errs.transaction, 'error'),
            },
        );
    };

    const columns: BiTableColumn<TransactionRow>[] = [
        {
            key: 'reference',
            label: 'Transaction',
            render: (t) => (
                <>
                    <Link
                        href={route('platform.finance.payments.show', t.id)}
                        className="font-medium text-indigo-600 hover:underline"
                    >
                        {t.reference_number}
                    </Link>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        {t.invoice_number ?? t.type}
                    </p>
                </>
            ),
        },
        {
            key: 'business',
            label: 'Business',
            render: (t) => (
                <>
                    <p className="text-gray-900 dark:text-gray-100">
                        {t.business?.name ?? '—'}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        {t.business?.owner_name}
                    </p>
                </>
            ),
        },
        {
            key: 'gateway',
            label: 'Gateway',
            render: (t) => t.gateway?.name ?? 'Manual',
        },
        {
            key: 'amount',
            label: 'Amount',
            render: (t) => `${formatCurrency(Number(t.amount))} ${t.currency}`,
        },
        {
            key: 'status',
            label: 'Status',
            render: (t) => (
                <BiBadge variant={STATUS_VARIANT[t.status] ?? 'neutral'}>
                    {t.status}
                </BiBadge>
            ),
        },
        {
            key: 'method',
            label: 'Method',
            render: (t) => t.payment_method ?? '—',
        },
        {
            key: 'actions',
            label: 'Actions',
            align: 'right',
            render: (t) => (
                <div className="flex justify-end gap-3">
                    {t.status === 'failed' && t.gateway && (
                        <button
                            onClick={() => retry(t)}
                            className="text-indigo-600 hover:underline"
                        >
                            Retry
                        </button>
                    )}
                    {t.status === 'pending' && (
                        <button
                            onClick={() => approve(t)}
                            className="text-emerald-600 hover:underline"
                        >
                            Approve
                        </button>
                    )}
                    {t.is_refundable && (
                        <button
                            onClick={() => openRefund(t)}
                            className="text-amber-600 hover:underline"
                        >
                            Refund
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <PlatformFinanceLayout title="Payments">
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {transactions.meta.total} total transactions.
                    </p>
                    <BiButton onClick={() => setRecording(true)}>
                        Record manual payment
                    </BiButton>
                </div>

                <BiDataGrid
                    columns={columns}
                    paginated={transactions}
                    rowKey={(t) => t.id}
                    emptyMessage="No transactions match these filters."
                    toolbar={
                        <>
                            <form
                                onSubmit={onSearchSubmit}
                                className="flex gap-2"
                            >
                                <TextInput
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search reference, invoice, business"
                                    className="w-72"
                                />
                                <SecondaryButton type="submit">
                                    Search
                                </SecondaryButton>
                            </form>

                            <SelectInput
                                value={filters.status ?? ''}
                                onChange={(e) =>
                                    applyFilters({ status: e.target.value })
                                }
                            >
                                <option value="">All statuses</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="successful">Successful</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="refunded">Refunded</option>
                                <option value="partially_refunded">
                                    Partially refunded
                                </option>
                                <option value="expired">Expired</option>
                            </SelectInput>

                            <SelectInput
                                value={filters.type ?? ''}
                                onChange={(e) =>
                                    applyFilters({ type: e.target.value })
                                }
                            >
                                <option value="">All types</option>
                                <option value="subscription_payment">
                                    Subscription payment
                                </option>
                                <option value="license_payment">
                                    License payment
                                </option>
                                <option value="renewal">Renewal</option>
                                <option value="upgrade">Upgrade</option>
                                <option value="manual">Manual</option>
                                <option value="refund">Refund</option>
                                <option value="partial_refund">
                                    Partial refund
                                </option>
                            </SelectInput>
                        </>
                    }
                />
            </div>

            <BiModal
                show={recording}
                onClose={() => setRecording(false)}
                title="Record a manual payment"
                maxWidth="2xl"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setRecording(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="manual-payment-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="manual-payment-form"
                    onSubmit={submitManual}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Business ID
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.business_id}
                            onChange={(e) =>
                                setData('business_id', e.target.value)
                            }
                            placeholder="UUID"
                        />
                        {errors.business_id && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.business_id}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Amount
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.amount}
                                onChange={(e) =>
                                    setData('amount', e.target.value)
                                }
                            />
                            {errors.amount && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.amount}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Currency
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.currency}
                                onChange={(e) =>
                                    setData('currency', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Type
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.type}
                                onChange={(e) =>
                                    setData('type', e.target.value)
                                }
                            >
                                <option value="manual">Manual</option>
                                <option value="subscription_payment">
                                    Subscription payment
                                </option>
                                <option value="license_payment">
                                    License payment
                                </option>
                                <option value="renewal">Renewal</option>
                                <option value="upgrade">Upgrade</option>
                            </SelectInput>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Tax
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.tax_amount}
                                onChange={(e) =>
                                    setData('tax_amount', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Discount
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.discount_amount}
                                onChange={(e) =>
                                    setData('discount_amount', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Fee
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.fee_amount}
                                onChange={(e) =>
                                    setData('fee_amount', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Commission
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.commission_amount}
                                onChange={(e) =>
                                    setData('commission_amount', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Payment method
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.payment_method}
                                onChange={(e) =>
                                    setData('payment_method', e.target.value)
                                }
                            >
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">
                                    Bank transfer
                                </option>
                                <option value="credit_card">Credit card</option>
                                <option value="debit_card">Debit card</option>
                                <option value="mobile_money">
                                    Mobile money
                                </option>
                                <option value="paypal">PayPal</option>
                                <option value="stripe">Stripe</option>
                                <option value="flutterwave">Flutterwave</option>
                                <option value="pesapal">Pesapal</option>
                                <option value="snippe">Snippe</option>
                                <option value="custom">Custom</option>
                            </SelectInput>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Invoice number
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.invoice_number}
                                onChange={(e) =>
                                    setData('invoice_number', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Notes
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </div>
                </form>
            </BiModal>

            <BiModal
                show={refunding !== null}
                onClose={() => setRefunding(null)}
                title={`Refund — ${refunding?.reference_number ?? ''}`}
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setRefunding(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton type="submit" form="refund-form">
                            Refund
                        </BiButton>
                    </>
                }
            >
                <form
                    id="refund-form"
                    onSubmit={submitRefund}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Amount
                        </label>
                        <TextInput
                            type="number"
                            className="mt-1 block w-full"
                            value={refundAmount}
                            onChange={(e) => setRefundAmount(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Reason
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={refundReason}
                            onChange={(e) => setRefundReason(e.target.value)}
                        />
                    </div>
                </form>
            </BiModal>
        </PlatformFinanceLayout>
    );
}
