import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { Budget } from '@/types/finance';
import { DocumentTextIcon, PlusIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface GlAccount {
    id: string;
    code: string;
    name: string;
}

interface Props {
    budgets: Budget[];
    glAccounts: GlAccount[];
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

interface BudgetLineRow {
    account_id: string;
    period_start: string;
    period_end: string;
    budgeted_amount: string;
    notes: string;
}

export default function BudgetsIndex({ budgets, glAccounts }: Props) {
    const [creating, setCreating] = useState(false);

    const thisYear = new Date().getFullYear();
    const emptyLine: BudgetLineRow = {
        account_id: '',
        period_start: `${thisYear}-01-01`,
        period_end: `${thisYear}-12-31`,
        budgeted_amount: '',
        notes: '',
    };

    const form = useForm<{
        name: string;
        fiscal_year: string;
        description: string;
        lines: BudgetLineRow[];
    }>({
        name: '',
        fiscal_year: String(thisYear),
        description: '',
        lines: [{ ...emptyLine }],
    });

    const addLine = () =>
        form.setData('lines', [...form.data.lines, { ...emptyLine }]);

    const removeLine = (idx: number) =>
        form.setData(
            'lines',
            form.data.lines.filter((_, i) => i !== idx),
        );

    const updateLine = (
        idx: number,
        key: keyof BudgetLineRow,
        value: string,
    ) => {
        const updated = [...form.data.lines];
        updated[idx] = { ...updated[idx], [key]: value };
        form.setData('lines', updated);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('finance.budgets.store'), {
            onSuccess: () => {
                setCreating(false);
                form.reset();
            },
        });
    };

    return (
        <FinanceLayout title="Budgets">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Budgets
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Create and manage fiscal year budgets
                    </p>
                </div>
                <PrimaryButton onClick={() => setCreating(true)}>
                    <PlusIcon className="mr-1.5 h-4 w-4" />
                    New Budget
                </PrimaryButton>
            </div>

            {budgets.length === 0 ? (
                <Card>
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <DocumentTextIcon className="mb-4 h-12 w-12 text-gray-300" />
                        <h4 className="text-base font-medium text-gray-900 dark:text-white">
                            No budgets yet
                        </h4>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Create a budget to track planned vs actual spending.
                        </p>
                    </div>
                </Card>
            ) : (
                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-700">
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Name
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Year
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Lines
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Status
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Approved
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {budgets.map((budget) => (
                                    <tr key={budget.id}>
                                        <td className="py-2 pr-4">
                                            <Link
                                                href={route(
                                                    'finance.budgets.show',
                                                    budget.id,
                                                )}
                                                className="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                            >
                                                {budget.name}
                                            </Link>
                                            {budget.description && (
                                                <p className="mt-0.5 line-clamp-1 text-xs text-gray-500">
                                                    {budget.description}
                                                </p>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                            {budget.fiscal_year}
                                        </td>
                                        <td className="py-2 pr-4 text-gray-600 dark:text-gray-400">
                                            {budget.lines_count}
                                        </td>
                                        <td className="py-2 pr-4">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANT[
                                                        budget.status
                                                    ]
                                                }
                                            >
                                                {budget.status
                                                    .charAt(0)
                                                    .toUpperCase() +
                                                    budget.status.slice(1)}
                                            </Badge>
                                        </td>
                                        <td className="py-2 text-gray-600 dark:text-gray-400">
                                            {budget.approved_at ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* Create Budget Modal */}
            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="2xl"
            >
                <form onSubmit={submit} className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        New Budget
                    </h3>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    htmlFor="b_name"
                                    value="Budget Name"
                                />
                                <TextInput
                                    id="b_name"
                                    className="mt-1 block w-full"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. FY 2026 Operating Budget"
                                    required
                                />
                                <InputError
                                    message={form.errors.name}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="b_year"
                                    value="Fiscal Year"
                                />
                                <TextInput
                                    id="b_year"
                                    type="number"
                                    className="mt-1 block w-full"
                                    value={form.data.fiscal_year}
                                    onChange={(e) =>
                                        form.setData(
                                            'fiscal_year',
                                            e.target.value,
                                        )
                                    }
                                    min={2000}
                                    max={2100}
                                    required
                                />
                                <InputError
                                    message={form.errors.fiscal_year}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="b_desc"
                                value="Description (optional)"
                            />
                            <textarea
                                id="b_desc"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:text-sm"
                                rows={2}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </div>

                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <InputLabel value="Budget Lines" />
                                <button
                                    type="button"
                                    onClick={addLine}
                                    className="text-xs text-indigo-600 hover:text-indigo-800"
                                >
                                    + Add Line
                                </button>
                            </div>
                            <div className="space-y-2">
                                {form.data.lines.map((line, idx) => (
                                    <div
                                        key={idx}
                                        className="grid grid-cols-12 gap-2 rounded border border-gray-100 p-2 dark:border-gray-700"
                                    >
                                        <div className="col-span-4">
                                            <SelectInput
                                                className="block w-full"
                                                value={line.account_id}
                                                onChange={(e) =>
                                                    updateLine(
                                                        idx,
                                                        'account_id',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            >
                                                <option value="">
                                                    Select account
                                                </option>
                                                {glAccounts.map((a) => (
                                                    <option
                                                        key={a.id}
                                                        value={a.id}
                                                    >
                                                        {a.code} — {a.name}
                                                    </option>
                                                ))}
                                            </SelectInput>
                                        </div>
                                        <div className="col-span-2">
                                            <TextInput
                                                type="date"
                                                className="block w-full text-sm"
                                                value={line.period_start}
                                                onChange={(e) =>
                                                    updateLine(
                                                        idx,
                                                        'period_start',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="col-span-2">
                                            <TextInput
                                                type="date"
                                                className="block w-full text-sm"
                                                value={line.period_end}
                                                onChange={(e) =>
                                                    updateLine(
                                                        idx,
                                                        'period_end',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="col-span-3">
                                            <TextInput
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="Amount"
                                                className="block w-full text-sm"
                                                value={line.budgeted_amount}
                                                onChange={(e) =>
                                                    updateLine(
                                                        idx,
                                                        'budgeted_amount',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="col-span-1 flex items-center justify-center">
                                            {form.data.lines.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeLine(idx)
                                                    }
                                                    className="text-red-500 hover:text-red-700"
                                                >
                                                    ×
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <InputError
                                message={
                                    (form.errors as Record<string, string>)
                                        .lines ?? ''
                                }
                                className="mt-1"
                            />
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreating(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Create Budget'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </FinanceLayout>
    );
}
