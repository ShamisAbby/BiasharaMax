import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { Budget, BudgetVsActual } from '@/types/finance';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Props {
    budget: Budget;
    vsActual: BudgetVsActual[];
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'neutral' | 'info'
> = {
    draft: 'neutral',
    approved: 'info',
    active: 'success',
    archived: 'warning',
};

type ConfirmAction = 'approve' | 'activate' | 'delete' | null;

export default function BudgetShow({ budget, vsActual }: Props) {
    const [confirming, setConfirming] = useState<ConfirmAction>(null);
    const [activeTab, setActiveTab] = useState<'lines' | 'vs-actual'>('lines');

    const actionForm = useForm({});

    const handleAction = (e: FormEvent) => {
        e.preventDefault();
        if (!confirming) return;

        const routes: Record<string, string> = {
            approve: route('finance.budgets.approve', budget.id),
            activate: route('finance.budgets.activate', budget.id),
            delete: route('finance.budgets.destroy', budget.id),
        };

        if (confirming === 'delete') {
            actionForm.delete(routes[confirming], {
                onSuccess: () => setConfirming(null),
            });
        } else {
            actionForm.post(routes[confirming], {
                onSuccess: () => setConfirming(null),
            });
        }
    };

    const totalBudgeted = vsActual.reduce(
        (sum, row) => sum + parseFloat(row.budgeted),
        0,
    );
    const totalActual = vsActual.reduce(
        (sum, row) => sum + parseFloat(row.actual),
        0,
    );
    const totalVariance = vsActual.reduce(
        (sum, row) => sum + parseFloat(row.variance),
        0,
    );

    return (
        <FinanceLayout title={budget.name}>
            <div className="flex items-center gap-3">
                <Link
                    href={route('finance.budgets.index')}
                    className="text-gray-400 hover:text-gray-600"
                >
                    <ArrowLeftIcon className="h-5 w-5" />
                </Link>
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {budget.name}
                        </h3>
                        <Badge variant={STATUS_VARIANT[budget.status]}>
                            {budget.status.charAt(0).toUpperCase() +
                                budget.status.slice(1)}
                        </Badge>
                        <span className="text-sm text-gray-500">
                            FY {budget.fiscal_year}
                        </span>
                    </div>
                    {budget.description && (
                        <p className="mt-0.5 text-sm text-gray-500">
                            {budget.description}
                        </p>
                    )}
                </div>
                <div className="flex gap-2">
                    {budget.status === 'draft' && (
                        <PrimaryButton onClick={() => setConfirming('approve')}>
                            Approve
                        </PrimaryButton>
                    )}
                    {budget.status === 'approved' && (
                        <PrimaryButton
                            onClick={() => setConfirming('activate')}
                        >
                            Activate
                        </PrimaryButton>
                    )}
                    {budget.status !== 'active' && (
                        <SecondaryButton
                            onClick={() => setConfirming('delete')}
                            className="text-red-600"
                        >
                            Delete
                        </SecondaryButton>
                    )}
                </div>
            </div>

            <div className="flex gap-4 border-b border-gray-200 dark:border-gray-700">
                <button
                    onClick={() => setActiveTab('lines')}
                    className={`pb-3 text-sm font-medium ${activeTab === 'lines' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700'}`}
                >
                    Budget Lines
                </button>
                <button
                    onClick={() => setActiveTab('vs-actual')}
                    className={`pb-3 text-sm font-medium ${activeTab === 'vs-actual' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700'}`}
                >
                    Budget vs Actual
                </button>
            </div>

            {activeTab === 'lines' && (
                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-700">
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Account
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Period
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Budgeted Amount
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Notes
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {(budget.lines ?? []).map((line) => (
                                    <tr key={line.id}>
                                        <td className="py-2 pr-4 font-medium text-gray-900 dark:text-white">
                                            <span className="text-xs text-gray-400">
                                                {line.account.code}
                                            </span>{' '}
                                            {line.account.name}
                                        </td>
                                        <td className="py-2 pr-4 text-gray-600 dark:text-gray-400">
                                            {line.period_start} →{' '}
                                            {line.period_end}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono text-gray-900 dark:text-white">
                                            {formatCurrency(
                                                line.budgeted_amount,
                                            )}
                                        </td>
                                        <td className="py-2 text-gray-500">
                                            {line.notes ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {activeTab === 'vs-actual' && (
                <Card>
                    {vsActual.length === 0 ? (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No budget lines to compare.
                        </p>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 dark:border-gray-700">
                                            <th className="pb-2 text-left font-medium text-gray-500">
                                                Account
                                            </th>
                                            <th className="pb-2 text-left font-medium text-gray-500">
                                                Period
                                            </th>
                                            <th className="pb-2 text-right font-medium text-gray-500">
                                                Budgeted
                                            </th>
                                            <th className="pb-2 text-right font-medium text-gray-500">
                                                Actual
                                            </th>
                                            <th className="pb-2 text-right font-medium text-gray-500">
                                                Variance
                                            </th>
                                            <th className="pb-2 text-right font-medium text-gray-500">
                                                Var %
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                        {vsActual.map((row, idx) => {
                                            const variance = parseFloat(
                                                row.variance,
                                            );
                                            const isOver = variance < 0;
                                            return (
                                                <tr key={idx}>
                                                    <td className="py-2 pr-4 text-gray-900 dark:text-white">
                                                        <span className="text-xs text-gray-400">
                                                            {row.account_code}
                                                        </span>{' '}
                                                        {row.account_name}
                                                    </td>
                                                    <td className="py-2 pr-4 text-xs text-gray-500">
                                                        {row.period_start} →{' '}
                                                        {row.period_end}
                                                    </td>
                                                    <td className="py-2 pr-4 text-right font-mono">
                                                        {formatCurrency(
                                                            parseFloat(
                                                                row.budgeted,
                                                            ),
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-4 text-right font-mono">
                                                        {formatCurrency(
                                                            parseFloat(
                                                                row.actual,
                                                            ),
                                                        )}
                                                    </td>
                                                    <td
                                                        className={`py-2 pr-4 text-right font-mono ${isOver ? 'text-red-600' : 'text-green-600'}`}
                                                    >
                                                        {formatCurrency(
                                                            Math.abs(variance),
                                                        )}
                                                        {isOver
                                                            ? ' over'
                                                            : ' under'}
                                                    </td>
                                                    <td
                                                        className={`py-2 text-right text-xs ${isOver ? 'text-red-500' : 'text-green-500'}`}
                                                    >
                                                        {row.variance_pct !==
                                                        null
                                                            ? `${parseFloat(row.variance_pct).toFixed(1)}%`
                                                            : '—'}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                    <tfoot className="border-t-2 border-gray-200 dark:border-gray-600">
                                        <tr className="font-semibold">
                                            <td
                                                colSpan={2}
                                                className="py-2 text-gray-700 dark:text-gray-300"
                                            >
                                                Total
                                            </td>
                                            <td className="py-2 pr-4 text-right font-mono">
                                                {formatCurrency(totalBudgeted)}
                                            </td>
                                            <td className="py-2 pr-4 text-right font-mono">
                                                {formatCurrency(totalActual)}
                                            </td>
                                            <td
                                                className={`py-2 pr-4 text-right font-mono ${totalVariance < 0 ? 'text-red-600' : 'text-green-600'}`}
                                            >
                                                {formatCurrency(
                                                    Math.abs(totalVariance),
                                                )}
                                                {totalVariance < 0
                                                    ? ' over'
                                                    : ' under'}
                                            </td>
                                            <td />
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </>
                    )}
                </Card>
            )}

            {/* Confirm Action Modal */}
            <Modal
                show={confirming !== null}
                onClose={() => setConfirming(null)}
                maxWidth="sm"
            >
                {confirming && (
                    <form onSubmit={handleAction} className="p-6">
                        <h3 className="mb-2 text-base font-semibold text-gray-900 dark:text-white">
                            {confirming === 'approve' && 'Approve Budget'}
                            {confirming === 'activate' && 'Activate Budget'}
                            {confirming === 'delete' && 'Delete Budget'}
                        </h3>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            {confirming === 'approve' &&
                                `Approve "${budget.name}"? This allows it to be activated as the active budget.`}
                            {confirming === 'activate' &&
                                `Activate "${budget.name}"? Any currently active budget for FY ${budget.fiscal_year} will be archived.`}
                            {confirming === 'delete' &&
                                `Delete "${budget.name}" and all its lines? This cannot be undone.`}
                        </p>
                        <div className="mt-6 flex justify-end gap-3">
                            <SecondaryButton
                                type="button"
                                onClick={() => setConfirming(null)}
                            >
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton
                                disabled={actionForm.processing}
                                className={
                                    confirming === 'delete'
                                        ? 'bg-red-600 hover:bg-red-700'
                                        : ''
                                }
                            >
                                {actionForm.processing
                                    ? 'Processing…'
                                    : 'Confirm'}
                            </PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>
        </FinanceLayout>
    );
}
