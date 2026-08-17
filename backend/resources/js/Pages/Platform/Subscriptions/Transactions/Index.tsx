import BiBadge from '@/Components/Bi/BiBadge';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PlatformSubscriptionsLayout from '@/Layouts/PlatformSubscriptionsLayout';
import { formatCurrency } from '@/lib/currency';
import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface TransactionRow {
    id: string;
    amount: string;
    currency: string;
    billing_cycle: string;
    status: 'paid' | 'pending' | 'refunded';
    payment_method: string | null;
    notes: string | null;
    business: { id: string; name: string } | null;
    recorded_by: string | null;
    paid_at: string;
}

interface PaginatedTransactions {
    data: TransactionRow[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const STATUS_VARIANT = {
    paid: 'success',
    pending: 'warning',
    refunded: 'danger',
} as const;

export default function SubscriptionTransactionsIndex({
    transactions,
    filters,
}: {
    transactions: PaginatedTransactions;
    filters: Record<string, string>;
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const onSearchSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('platform.subscriptions.transactions.index'),
            { ...filters, search },
            { preserveState: true, replace: true },
        );
    };

    const columns: BiTableColumn<TransactionRow>[] = [
        {
            key: 'business',
            label: 'Business',
            render: (t) => t.business?.name ?? '—',
        },
        {
            key: 'amount',
            label: 'Amount',
            render: (t) => formatCurrency(t.amount),
        },
        {
            key: 'cycle',
            label: 'Billing cycle',
            render: (t) => (
                <span className="capitalize">{t.billing_cycle}</span>
            ),
        },
        {
            key: 'method',
            label: 'Method',
            render: (t) => t.payment_method ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            render: (t) => (
                <BiBadge variant={STATUS_VARIANT[t.status]}>{t.status}</BiBadge>
            ),
        },
        {
            key: 'recorded_by',
            label: 'Recorded by',
            render: (t) => t.recorded_by ?? '—',
        },
        {
            key: 'paid_at',
            label: 'Paid at',
            align: 'right',
            render: (t) => new Date(t.paid_at).toLocaleString(),
        },
    ];

    return (
        <PlatformSubscriptionsLayout title="Transactions">
            <BiDataGrid
                title="Transactions"
                description={`${transactions.meta.total} recorded payments — renewals are recorded from the Subscribers tab`}
                columns={columns}
                paginated={transactions}
                rowKey={(t) => t.id}
                emptyMessage="No transactions recorded yet."
                toolbar={
                    <form onSubmit={onSearchSubmit} className="flex gap-2">
                        <TextInput
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by business name"
                            className="w-72"
                        />
                        <SecondaryButton type="submit">Search</SecondaryButton>
                    </form>
                }
            />
        </PlatformSubscriptionsLayout>
    );
}
