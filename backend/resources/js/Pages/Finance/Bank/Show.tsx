import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import {
    BankAccount,
    BankReconciliation,
    BankTransaction,
} from '@/types/finance';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface PaginatedTransactions {
    data: BankTransaction[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    bankAccount: BankAccount & { id: string };
    transactions: PaginatedTransactions;
    reconciliations: BankReconciliation[];
}

const TYPE_LABEL: Record<string, string> = {
    debit: 'Debit (In)',
    credit: 'Credit (Out)',
    transfer: 'Transfer',
};

const STATUS_VARIANT: Record<string, 'success' | 'neutral' | 'warning'> = {
    reconciled: 'success',
    unreconciled: 'warning',
    void: 'neutral',
};

export default function BankShow({
    bankAccount,
    transactions,
    reconciliations,
}: Props) {
    const [reconciling, setReconciling] = useState(false);

    const reconForm = useForm({
        period_start: '',
        period_end: new Date().toISOString().slice(0, 10),
        statement_balance: '',
    });

    const submitRecon = (e: FormEvent) => {
        e.preventDefault();
        reconForm.post(
            route('finance.bank.reconciliations.start', bankAccount.id),
            {
                onSuccess: () => {
                    setReconciling(false);
                    reconForm.reset();
                },
            },
        );
    };

    return (
        <FinanceLayout title={bankAccount.bank_name}>
            <div className="flex items-center gap-3">
                <Link
                    href={route('finance.bank.index')}
                    className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <ArrowLeftIcon className="h-5 w-5" />
                </Link>
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        {bankAccount.bank_name}
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {bankAccount.account_number} ·{' '}
                        {bankAccount.account_holder_name}
                    </p>
                </div>
                <div className="ml-auto flex items-center gap-3">
                    <div className="text-right">
                        <p className="text-xs text-gray-500">Current Balance</p>
                        <p className="text-xl font-bold text-gray-900 dark:text-white">
                            {formatCurrency(bankAccount.current_balance)}
                        </p>
                    </div>
                    <PrimaryButton onClick={() => setReconciling(true)}>
                        Reconcile
                    </PrimaryButton>
                </div>
            </div>

            {reconciliations.length > 0 && (
                <Card title="Reconciliations">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-gray-700">
                                    <th className="pb-2 pr-4">Period</th>
                                    <th className="pb-2 pr-4 text-right">
                                        Statement
                                    </th>
                                    <th className="pb-2 pr-4 text-right">
                                        Book
                                    </th>
                                    <th className="pb-2 pr-4 text-right">
                                        Difference
                                    </th>
                                    <th className="pb-2">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {reconciliations.map((r) => (
                                    <tr key={r.id}>
                                        <td className="py-2 pr-4 text-gray-900 dark:text-white">
                                            {r.period_start} — {r.period_end}
                                        </td>
                                        <td className="py-2 pr-4 text-right">
                                            {formatCurrency(
                                                r.statement_balance,
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 text-right">
                                            {formatCurrency(r.book_balance)}
                                        </td>
                                        <td
                                            className={`py-2 pr-4 text-right font-medium ${r.difference === 0 ? 'text-emerald-600' : 'text-red-600'}`}
                                        >
                                            {formatCurrency(r.difference)}
                                        </td>
                                        <td className="py-2">
                                            <Badge
                                                variant={
                                                    r.status === 'completed'
                                                        ? 'success'
                                                        : 'warning'
                                                }
                                            >
                                                {r.status === 'completed'
                                                    ? 'Completed'
                                                    : 'Draft'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            <Card title={`Transactions (${transactions.total})`}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-gray-700">
                                <th className="pb-2 pr-4">Date</th>
                                <th className="pb-2 pr-4">Type</th>
                                <th className="pb-2 pr-4">Description</th>
                                <th className="pb-2 pr-4">Reference</th>
                                <th className="pb-2 pr-4 text-right">Amount</th>
                                <th className="pb-2">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                            {transactions.data.map((t) => (
                                <tr key={t.id}>
                                    <td className="py-2 pr-4 text-gray-500">
                                        {t.transaction_date}
                                    </td>
                                    <td className="py-2 pr-4">
                                        <span
                                            className={`text-xs font-medium ${t.type === 'debit' ? 'text-emerald-600' : 'text-red-600'}`}
                                        >
                                            {TYPE_LABEL[t.type] ?? t.type}
                                        </span>
                                    </td>
                                    <td className="py-2 pr-4 text-gray-900 dark:text-white">
                                        {t.description ?? '—'}
                                    </td>
                                    <td className="py-2 pr-4 text-gray-500">
                                        {t.reference ?? '—'}
                                    </td>
                                    <td
                                        className={`py-2 pr-4 text-right font-medium ${t.type === 'debit' ? 'text-emerald-600' : 'text-red-600'}`}
                                    >
                                        {t.type === 'credit' ? '-' : ''}
                                        {formatCurrency(t.amount)}
                                    </td>
                                    <td className="py-2">
                                        <Badge
                                            variant={
                                                STATUS_VARIANT[
                                                    t.reconciliation_status
                                                ] ?? 'neutral'
                                            }
                                        >
                                            {t.reconciliation_status}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {transactions.last_page > 1 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {transactions.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="cursor-not-allowed rounded px-3 py-1 text-sm text-gray-400"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                )}
            </Card>

            {/* Start Reconciliation Modal */}
            <Modal show={reconciling} onClose={() => setReconciling(false)}>
                <form onSubmit={submitRecon} className="space-y-4 p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Start Reconciliation
                    </h2>
                    <p className="text-sm text-gray-500">
                        Enter the period and your bank statement's closing
                        balance.
                    </p>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Period Start" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full"
                                value={reconForm.data.period_start}
                                onChange={(e) =>
                                    reconForm.setData(
                                        'period_start',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={reconForm.errors.period_start}
                            />
                        </div>
                        <div>
                            <InputLabel value="Period End" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full"
                                value={reconForm.data.period_end}
                                onChange={(e) =>
                                    reconForm.setData(
                                        'period_end',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError message={reconForm.errors.period_end} />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Bank Statement Closing Balance" />
                        <TextInput
                            type="number"
                            step="0.01"
                            className="mt-1 w-full"
                            value={reconForm.data.statement_balance}
                            onChange={(e) =>
                                reconForm.setData(
                                    'statement_balance',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={reconForm.errors.statement_balance}
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <SecondaryButton
                            type="button"
                            onClick={() => setReconciling(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={reconForm.processing}
                        >
                            Start
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </FinanceLayout>
    );
}
