import Badge from '@/Components/Badge';
import Checkbox from '@/Components/Checkbox';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { Account, AccountType, NormalBalance } from '@/types/finance';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const TYPE_LABEL: Record<AccountType, string> = {
    asset: 'Asset',
    liability: 'Liability',
    equity: 'Equity',
    income: 'Income',
    expense: 'Expense',
};

export default function AccountsIndex({ accounts }: { accounts: Account[] }) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);

    const createForm = useForm({
        code: '',
        name: '',
        description: '',
        type: 'asset' as AccountType,
        normal_balance: 'debit' as NormalBalance,
        parent_account_id: '',
    });

    const editForm = useForm({
        name: '',
        description: '',
        is_active: true as boolean,
        parent_account_id: '',
    });

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('finance.accounts.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const openEdit = (account: Account) => {
        editForm.setData({
            name: account.name,
            description: account.description ?? '',
            is_active: account.is_active,
            parent_account_id: account.parent_account_id ?? '',
        });
        setEditing(account);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('finance.accounts.update', editing.id), {
            onSuccess: () => setEditing(null),
        });
    };

    const destroy = (account: Account) => {
        askConfirm({
            title: `Delete the "${account.name}" account?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('finance.accounts.destroy', account.id));
            },
        });
    };

    return (
        <FinanceLayout title="Chart of Accounts">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Chart of Accounts
                </h3>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Add Account
                </PrimaryButton>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Code',
                                'Name',
                                'Type',
                                'Normal Balance',
                                'Parent',
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
                        {accounts.map((account) => (
                            <tr
                                key={account.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {account.code}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {account.name}
                                    {account.is_system_default && (
                                        <span className="ml-2">
                                            <Badge variant="neutral">
                                                System
                                            </Badge>
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {TYPE_LABEL[account.type]}
                                </td>
                                <td className="px-4 py-3 text-sm capitalize text-gray-500 dark:text-gray-400">
                                    {account.normal_balance}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {account.parent
                                        ? `${account.parent.code} — ${account.parent.name}`
                                        : '—'}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={
                                            account.is_active
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {account.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <button
                                        onClick={() => openEdit(account)}
                                        className="mr-3 text-indigo-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                    {!account.is_system_default && (
                                        <button
                                            onClick={() => destroy(account)}
                                            className="text-red-600 hover:underline"
                                        >
                                            Delete
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {accounts.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No accounts yet. Add one to start building your Chart of
                        Accounts.
                    </p>
                )}
            </div>

            <Modal show={creating} onClose={() => setCreating(false)}>
                <form onSubmit={submitCreate} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        New account
                    </h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Code" />
                            <TextInput
                                className="mt-1 block w-full"
                                value={createForm.data.code}
                                onChange={(e) =>
                                    createForm.setData('code', e.target.value)
                                }
                            />
                            <InputError
                                message={createForm.errors.code}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Name" />
                            <TextInput
                                className="mt-1 block w-full"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                            />
                            <InputError
                                message={createForm.errors.name}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Type" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={createForm.data.type}
                                onChange={(e) =>
                                    createForm.setData(
                                        'type',
                                        e.target.value as AccountType,
                                    )
                                }
                            >
                                {Object.entries(TYPE_LABEL).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </SelectInput>
                            <InputError
                                message={createForm.errors.type}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Normal Balance" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={createForm.data.normal_balance}
                                onChange={(e) =>
                                    createForm.setData(
                                        'normal_balance',
                                        e.target.value as NormalBalance,
                                    )
                                }
                            >
                                <option value="debit">Debit</option>
                                <option value="credit">Credit</option>
                            </SelectInput>
                            <InputError
                                message={createForm.errors.normal_balance}
                                className="mt-1"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel value="Parent Account (optional)" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={createForm.data.parent_account_id}
                                onChange={(e) =>
                                    createForm.setData(
                                        'parent_account_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">None</option>
                                {accounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.code} — {a.name}
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError
                                message={createForm.errors.parent_account_id}
                                className="mt-1"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel value="Description" />
                            <TextInput
                                className="mt-1 block w-full"
                                value={createForm.data.description}
                                onChange={(e) =>
                                    createForm.setData(
                                        'description',
                                        e.target.value,
                                    )
                                }
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
                        <PrimaryButton
                            type="submit"
                            disabled={createForm.processing}
                        >
                            Add Account
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal show={editing !== null} onClose={() => setEditing(null)}>
                <form onSubmit={submitEdit} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Edit {editing?.code} — {editing?.name}
                    </h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <InputLabel value="Name" />
                            <TextInput
                                className="mt-1 block w-full"
                                value={editForm.data.name}
                                onChange={(e) =>
                                    editForm.setData('name', e.target.value)
                                }
                            />
                            <InputError
                                message={editForm.errors.name}
                                className="mt-1"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel value="Parent Account (optional)" />
                            <SelectInput
                                className="mt-1 block w-full"
                                value={editForm.data.parent_account_id}
                                onChange={(e) =>
                                    editForm.setData(
                                        'parent_account_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">None</option>
                                {accounts
                                    .filter((a) => a.id !== editing?.id)
                                    .map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.code} — {a.name}
                                        </option>
                                    ))}
                            </SelectInput>
                            <InputError
                                message={editForm.errors.parent_account_id}
                                className="mt-1"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel value="Description" />
                            <TextInput
                                className="mt-1 block w-full"
                                value={editForm.data.description}
                                onChange={(e) =>
                                    editForm.setData(
                                        'description',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 sm:col-span-2">
                            <Checkbox
                                checked={editForm.data.is_active}
                                onChange={(e) =>
                                    editForm.setData(
                                        'is_active',
                                        e.target.checked,
                                    )
                                }
                            />
                            Active
                        </label>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setEditing(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={editForm.processing}
                        >
                            Save Changes
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </FinanceLayout>
    );
}
