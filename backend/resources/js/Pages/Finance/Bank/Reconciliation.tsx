import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import PrimaryButton from '@/Components/PrimaryButton';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { BankReconciliation } from '@/types/finance';
import { ArrowLeftIcon, CheckCircleIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';

interface BankAccountSummary {
    id: string;
    bank_name: string;
    account_number: string;
    current_balance: number;
}

interface Transaction {
    id: string;
    transaction_date: string;
    type: 'debit' | 'credit' | 'transfer';
    amount: number;
    reference: string | null;
    description: string | null;
    reconciliation_status: 'unreconciled' | 'reconciled' | 'void';
    reconciled_at: string | null;
}

interface Props {
    bankAccount: BankAccountSummary;
    reconciliations: BankReconciliation[];
}

export default function ReconciliationIndex({
    bankAccount,
    reconciliations,
}: Props) {
    const draftRecon =
        reconciliations.find((r) => r.status === 'draft') ?? null;

    const completeForm = useForm({});

    const submitComplete = (reconciliationId: string) => {
        completeForm.post(
            route('finance.bank.reconciliations.complete', [
                bankAccount.id,
                reconciliationId,
            ]),
        );
    };

    return (
        <FinanceLayout title="Reconciliations">
            <div className="flex items-center gap-3">
                <Link
                    href={route('finance.bank.show', bankAccount.id)}
                    className="text-gray-500 hover:text-gray-700 dark:text-gray-400"
                >
                    <ArrowLeftIcon className="h-5 w-5" />
                </Link>
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        {bankAccount.bank_name} Reconciliations
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {bankAccount.account_number}
                    </p>
                </div>
            </div>

            {draftRecon && (
                <Card
                    title="Active Reconciliation"
                    description={`${draftRecon.period_start} — ${draftRecon.period_end}`}
                >
                    <div className="mb-4 grid gap-4 sm:grid-cols-3">
                        <div className="rounded-lg bg-gray-50 p-3 dark:bg-gray-700">
                            <p className="text-xs text-gray-500">
                                Statement Balance
                            </p>
                            <p className="text-lg font-semibold">
                                {formatCurrency(draftRecon.statement_balance)}
                            </p>
                        </div>
                        <div className="rounded-lg bg-gray-50 p-3 dark:bg-gray-700">
                            <p className="text-xs text-gray-500">
                                Book Balance
                            </p>
                            <p className="text-lg font-semibold">
                                {formatCurrency(draftRecon.book_balance)}
                            </p>
                        </div>
                        <div
                            className={`rounded-lg p-3 ${draftRecon.difference === 0 ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20'}`}
                        >
                            <p className="text-xs text-gray-500">Difference</p>
                            <p
                                className={`text-lg font-semibold ${draftRecon.difference === 0 ? 'text-emerald-600' : 'text-red-600'}`}
                            >
                                {formatCurrency(draftRecon.difference)}
                            </p>
                        </div>
                    </div>
                    {draftRecon.difference === 0 && (
                        <div className="flex items-center gap-3">
                            <CheckCircleIcon className="h-5 w-5 text-emerald-500" />
                            <p className="text-sm text-emerald-700 dark:text-emerald-400">
                                Reconciliation is balanced. Ready to complete.
                            </p>
                            <PrimaryButton
                                onClick={() => submitComplete(draftRecon.id)}
                                disabled={completeForm.processing}
                                className="ml-auto"
                            >
                                Complete Reconciliation
                            </PrimaryButton>
                        </div>
                    )}
                </Card>
            )}

            <Card title="All Reconciliations">
                {reconciliations.length === 0 ? (
                    <p className="text-sm text-gray-500">
                        No reconciliations yet. Go back to the account and click
                        Reconcile.
                    </p>
                ) : (
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
                                    <th className="pb-2 pr-4">Completed</th>
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
                                        <td className="py-2 pr-4 text-gray-500">
                                            {r.completed_at
                                                ? r.completed_at.slice(0, 10)
                                                : '—'}
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
                )}
            </Card>
        </FinanceLayout>
    );
}
