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
import { Expense, ExpenseCategory, ExpenseStatus } from '@/types/accounting';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Option = { id: string; name: string };

const STATUS_VARIANT: Record<
    ExpenseStatus,
    'neutral' | 'success' | 'warning' | 'danger'
> = {
    pending: 'warning',
    approved: 'neutral',
    rejected: 'danger',
    paid: 'success',
};

interface ExpenseFormData {
    title: string;
    description: string;
    expense_category_id: string;
    supplier_id: string;
    employee_id: string;
    branch_id: string;
    amount: string;
    expense_date: string;
    payment_method: string;
    is_recurring: boolean;
    recurrence_frequency: string;
    notes: string;
    receipt: File | null;
}

const emptyForm: ExpenseFormData = {
    title: '',
    description: '',
    expense_category_id: '',
    supplier_id: '',
    employee_id: '',
    branch_id: '',
    amount: '',
    expense_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    is_recurring: false,
    recurrence_frequency: '',
    notes: '',
    receipt: null,
};

export default function ExpensesIndex({
    expenses,
    categories,
    suppliers,
    branches,
    filters,
}: {
    expenses: {
        data: Expense[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    categories: ExpenseCategory[];
    suppliers: Option[];
    branches: Option[];
    filters: { search?: string; status?: string; expense_category_id?: string };
}) {
    const askConfirm = useConfirm();
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Expense | null>(null);
    const [rejecting, setRejecting] = useState<Expense | null>(null);

    const createForm = useForm<ExpenseFormData>(emptyForm);
    const editForm = useForm<ExpenseFormData>(emptyForm);
    const rejectForm = useForm({ rejection_reason: '' });

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('accounting.expenses.index'),
            { ...filters, search },
            { preserveState: true },
        );
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('accounting.expenses.store'), {
            forceFormData: true,
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const openEdit = (expense: Expense) => {
        editForm.setData({
            title: expense.title,
            description: expense.description ?? '',
            expense_category_id: expense.category?.id ?? '',
            supplier_id: expense.supplier?.id ?? '',
            employee_id: expense.employee?.id ?? '',
            branch_id: expense.branch?.id ?? '',
            amount: expense.amount,
            expense_date: expense.expense_date,
            payment_method: expense.payment_method,
            is_recurring: expense.is_recurring,
            recurrence_frequency: expense.recurrence_frequency ?? '',
            notes: expense.notes ?? '',
            receipt: null,
        });
        setEditing(expense);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.transform((data) => ({ ...data, _method: 'patch' }));
        editForm.post(route('accounting.expenses.update', editing.id), {
            forceFormData: true,
            onSuccess: () => setEditing(null),
        });
    };

    const approve = (expense: Expense) => {
        router.post(route('accounting.expenses.approve', expense.id));
    };

    const submitReject = (e: FormEvent) => {
        e.preventDefault();
        if (!rejecting) return;
        rejectForm.post(route('accounting.expenses.reject', rejecting.id), {
            onSuccess: () => {
                setRejecting(null);
                rejectForm.reset();
            },
        });
    };

    const markPaid = (expense: Expense) => {
        router.post(route('accounting.expenses.mark-paid', expense.id));
    };

    const destroy = (expense: Expense) => {
        askConfirm({
            title: `Delete the expense "${expense.title}"?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('accounting.expenses.destroy', expense.id));
            },
        });
    };

    return (
        <AccountingLayout title="Expenses">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={submitSearch} className="flex gap-2">
                    <TextInput
                        placeholder="Search expenses..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-64"
                    />
                    <SelectInput
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get(
                                route('accounting.expenses.index'),
                                {
                                    ...filters,
                                    status: e.target.value || undefined,
                                },
                                { preserveState: true },
                            )
                        }
                    >
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="paid">Paid</option>
                    </SelectInput>
                </form>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Add Expense
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
                                'Status',
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
                        {expenses.data.map((expense) => (
                            <tr
                                key={expense.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {expense.title}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {expense.category?.name ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {formatCurrency(expense.amount)}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {expense.expense_date}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={STATUS_VARIANT[expense.status]}
                                    >
                                        {expense.status}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <div className="flex justify-end gap-3">
                                        {expense.status === 'pending' && (
                                            <>
                                                <button
                                                    onClick={() =>
                                                        approve(expense)
                                                    }
                                                    className="text-emerald-600 hover:underline"
                                                >
                                                    Approve
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        setRejecting(expense)
                                                    }
                                                    className="text-red-600 hover:underline"
                                                >
                                                    Reject
                                                </button>
                                            </>
                                        )}
                                        {expense.status === 'approved' && (
                                            <button
                                                onClick={() =>
                                                    markPaid(expense)
                                                }
                                                className="text-indigo-600 hover:underline"
                                            >
                                                Mark Paid
                                            </button>
                                        )}
                                        <button
                                            onClick={() => openEdit(expense)}
                                            className="text-gray-600 hover:underline dark:text-gray-300"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() => destroy(expense)}
                                            className="text-red-600 hover:underline"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {expenses.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No expenses recorded yet. Add one to start tracking
                        spending.
                    </p>
                )}
            </div>

            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="xl"
            >
                <ExpenseForm
                    form={createForm}
                    categories={categories}
                    suppliers={suppliers}
                    branches={branches}
                    onSubmit={submitCreate}
                    onCancel={() => setCreating(false)}
                    submitLabel="Add Expense"
                />
            </Modal>

            <Modal
                show={editing !== null}
                onClose={() => setEditing(null)}
                maxWidth="xl"
            >
                <ExpenseForm
                    form={editForm}
                    categories={categories}
                    suppliers={suppliers}
                    branches={branches}
                    onSubmit={submitEdit}
                    onCancel={() => setEditing(null)}
                    submitLabel="Save Changes"
                />
            </Modal>

            <Modal show={rejecting !== null} onClose={() => setRejecting(null)}>
                <form onSubmit={submitReject} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Reject expense
                    </h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Tell {rejecting?.employee?.name ?? 'the submitter'} why
                        “{rejecting?.title}” is being rejected.
                    </p>
                    <div className="mt-4">
                        <textarea
                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            rows={3}
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
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setRejecting(null)}
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
        </AccountingLayout>
    );
}

function ExpenseForm({
    form,
    categories,
    suppliers,
    branches,
    onSubmit,
    onCancel,
    submitLabel,
}: {
    form: ReturnType<typeof useForm<ExpenseFormData>>;
    categories: ExpenseCategory[];
    suppliers: Option[];
    branches: Option[];
    onSubmit: (e: FormEvent) => void;
    onCancel: () => void;
    submitLabel: string;
}) {
    return (
        <form onSubmit={onSubmit} className="p-6">
            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                Expense details
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
                <div>
                    <TextInput
                        type="date"
                        className="block w-full"
                        value={form.data.expense_date}
                        onChange={(e) =>
                            form.setData('expense_date', e.target.value)
                        }
                    />
                    <InputError
                        message={form.errors.expense_date}
                        className="mt-2"
                    />
                </div>
                <SelectInput
                    value={form.data.expense_category_id}
                    onChange={(e) =>
                        form.setData('expense_category_id', e.target.value)
                    }
                >
                    <option value="">No category</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                        </option>
                    ))}
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
                    value={form.data.supplier_id}
                    onChange={(e) =>
                        form.setData('supplier_id', e.target.value)
                    }
                >
                    <option value="">No vendor</option>
                    {suppliers.map((supplier) => (
                        <option key={supplier.id} value={supplier.id}>
                            {supplier.name}
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
                <div className="sm:col-span-2">
                    <input
                        type="file"
                        accept=".jpg,.jpeg,.png,.pdf"
                        className="block w-full text-sm text-gray-600 dark:text-gray-300"
                        onChange={(e) =>
                            form.setData('receipt', e.target.files?.[0] ?? null)
                        }
                    />
                    <InputError
                        message={form.errors.receipt}
                        className="mt-2"
                    />
                </div>
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
