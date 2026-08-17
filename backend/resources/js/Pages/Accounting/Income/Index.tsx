import Badge from '@/Components/Badge';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AccountingLayout from '@/Layouts/AccountingLayout';
import { formatCurrency } from '@/lib/currency';
import { Income, IncomeCategory } from '@/types/accounting';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Option = { id: string; name: string };

const CATEGORY_VARIANT: Record<IncomeCategory, 'neutral' | 'info' | 'success'> =
    {
        service: 'info',
        other: 'neutral',
        manual: 'success',
    };

interface IncomeFormData {
    title: string;
    description: string;
    category: IncomeCategory;
    customer_id: string;
    branch_id: string;
    amount: string;
    income_date: string;
    payment_method: string;
    notes: string;
}

const emptyForm: IncomeFormData = {
    title: '',
    description: '',
    category: 'other',
    customer_id: '',
    branch_id: '',
    amount: '',
    income_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    notes: '',
};

export default function IncomeIndex({
    incomes,
    customers,
    branches,
    filters,
}: {
    incomes: {
        data: Income[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    customers: Option[];
    branches: Option[];
    filters: { search?: string; category?: string };
}) {
    const askConfirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Income | null>(null);

    const createForm = useForm<IncomeFormData>(emptyForm);
    const editForm = useForm<IncomeFormData>(emptyForm);

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('accounting.income.index'),
            { ...filters, search },
            { preserveState: true },
        );
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('accounting.income.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const openEdit = (income: Income) => {
        editForm.setData({
            title: income.title,
            description: income.description ?? '',
            category: income.category,
            customer_id: income.customer?.id ?? '',
            branch_id: income.branch?.id ?? '',
            amount: income.amount,
            income_date: income.income_date,
            payment_method: income.payment_method,
            notes: income.notes ?? '',
        });
        setEditing(income);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('accounting.income.update', editing.id), {
            onSuccess: () => setEditing(null),
        });
    };

    const destroy = (income: Income) => {
        askConfirm({
            title: `Delete the income entry "${income.title}"?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('accounting.income.destroy', income.id));
            },
        });
    };

    return (
        <AccountingLayout title="Income">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={submitSearch} className="flex gap-2">
                    <TextInput
                        placeholder="Search income..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-64"
                    />
                </form>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Add Income
                </PrimaryButton>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Title',
                                'Category',
                                'Amount',
                                'Date',
                                'Customer',
                                '',
                            ].map((h) => (
                                <th
                                    key={h}
                                    className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {incomes.data.map((income) => (
                            <tr
                                key={income.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {income.title}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={
                                            CATEGORY_VARIANT[income.category]
                                        }
                                    >
                                        {income.category}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {formatCurrency(income.amount)}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {income.income_date}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {income.customer?.name ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <button
                                        onClick={() => openEdit(income)}
                                        className="mr-3 text-indigo-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        onClick={() => destroy(income)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {incomes.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No income entries yet. Sales revenue is tracked
                        automatically — use this for service, other, or manual
                        income.
                    </p>
                )}
            </div>

            <Modal show={creating} onClose={() => setCreating(false)}>
                <IncomeForm
                    form={createForm}
                    customers={customers}
                    branches={branches}
                    onSubmit={submitCreate}
                    onCancel={() => setCreating(false)}
                    submitLabel="Add Income"
                />
            </Modal>

            <Modal show={editing !== null} onClose={() => setEditing(null)}>
                <IncomeForm
                    form={editForm}
                    customers={customers}
                    branches={branches}
                    onSubmit={submitEdit}
                    onCancel={() => setEditing(null)}
                    submitLabel="Save Changes"
                />
            </Modal>
        </AccountingLayout>
    );
}

function IncomeForm({
    form,
    customers,
    branches,
    onSubmit,
    onCancel,
    submitLabel,
}: {
    form: ReturnType<typeof useForm<IncomeFormData>>;
    customers: Option[];
    branches: Option[];
    onSubmit: (e: FormEvent) => void;
    onCancel: () => void;
    submitLabel: string;
}) {
    return (
        <form onSubmit={onSubmit} className="p-6">
            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                Income details
            </h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <TextInput
                        placeholder="Title"
                        className="block w-full"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                    <InputError message={form.errors.title} className="mt-2" />
                </div>
                <div>
                    <TextInput
                        type="number"
                        step="0.01"
                        placeholder="Amount"
                        className="block w-full"
                        value={form.data.amount}
                        onChange={(e) => form.setData('amount', e.target.value)}
                    />
                    <InputError message={form.errors.amount} className="mt-2" />
                </div>
                <TextInput
                    type="date"
                    className="block w-full"
                    value={form.data.income_date}
                    onChange={(e) =>
                        form.setData('income_date', e.target.value)
                    }
                />
                <SelectInput
                    value={form.data.category}
                    onChange={(e) =>
                        form.setData(
                            'category',
                            e.target.value as IncomeCategory,
                        )
                    }
                >
                    <option value="service">Service Income</option>
                    <option value="other">Other Income</option>
                    <option value="manual">Manual Entry</option>
                </SelectInput>
                <SelectInput
                    value={form.data.payment_method}
                    onChange={(e) =>
                        form.setData('payment_method', e.target.value)
                    }
                >
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="card">Card</option>
                    <option value="cheque">Cheque</option>
                    <option value="other">Other</option>
                </SelectInput>
                <SelectInput
                    value={form.data.customer_id}
                    onChange={(e) =>
                        form.setData('customer_id', e.target.value)
                    }
                >
                    <option value="">No customer</option>
                    {customers.map((customer) => (
                        <option key={customer.id} value={customer.id}>
                            {customer.name}
                        </option>
                    ))}
                </SelectInput>
                <SelectInput
                    value={form.data.branch_id}
                    onChange={(e) => form.setData('branch_id', e.target.value)}
                >
                    <option value="">No branch</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </SelectInput>
                <textarea
                    placeholder="Notes"
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:col-span-2"
                    rows={2}
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                />
            </div>
            <div className="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" onClick={onCancel}>
                    Cancel
                </SecondaryButton>
                <PrimaryButton type="submit" disabled={form.processing}>
                    {submitLabel}
                </PrimaryButton>
            </div>
        </form>
    );
}
