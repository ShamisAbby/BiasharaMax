import Badge from '@/Components/Badge';
import BiEmptyState from '@/Components/Bi/BiEmptyState';
import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { BankAccount } from '@/types/finance';
import {
    ArrowsRightLeftIcon,
    BuildingLibraryIcon,
    PlusIcon,
} from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface GlAccount {
    id: string;
    code: string;
    name: string;
}

interface Props {
    bankAccounts: BankAccount[];
    glAccounts: GlAccount[];
}

export default function BankIndex({ bankAccounts, glAccounts }: Props) {
    const [creating, setCreating] = useState(false);
    const [transferring, setTransferring] = useState(false);

    const createForm = useForm({
        account_id: '',
        bank_name: '',
        account_number: '',
        account_holder_name: '',
        opening_balance: '',
        opening_date: new Date().toISOString().slice(0, 10),
    });

    const transferForm = useForm({
        from_bank_account_id: '',
        to_bank_account_id: '',
        amount: '',
        date: new Date().toISOString().slice(0, 10),
        reference: '',
        description: '',
    });

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('finance.bank.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const submitTransfer = (e: FormEvent) => {
        e.preventDefault();
        transferForm.post(route('finance.bank.transfer'), {
            onSuccess: () => {
                setTransferring(false);
                transferForm.reset();
            },
        });
    };

    const totalBalance = bankAccounts.reduce(
        (sum, ba) => sum + ba.current_balance,
        0,
    );

    return (
        <FinanceLayout title="Bank Accounts">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Bank Accounts
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Manage your business bank and cash accounts
                    </p>
                </div>
                <div className="flex gap-2">
                    {bankAccounts.length >= 2 && (
                        <SecondaryButton onClick={() => setTransferring(true)}>
                            <ArrowsRightLeftIcon className="mr-1.5 h-4 w-4" />
                            Transfer
                        </SecondaryButton>
                    )}
                    <PrimaryButton onClick={() => setCreating(true)}>
                        <PlusIcon className="mr-1.5 h-4 w-4" />
                        Add Account
                    </PrimaryButton>
                </div>
            </div>

            {bankAccounts.length === 0 ? (
                <BiEmptyState
                    title="No bank accounts yet"
                    description="Link your bank and cash accounts to the General Ledger to enable reconciliation."
                    icon={<BuildingLibraryIcon className="h-10 w-10" />}
                />
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {bankAccounts.map((ba) => (
                            <Link
                                key={ba.id}
                                href={route('finance.bank.show', ba.id)}
                                className="block"
                            >
                                <Card>
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="font-semibold text-gray-900 dark:text-white">
                                                {ba.bank_name}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {ba.account_number}
                                            </p>
                                            {ba.account && (
                                                <p className="mt-1 text-xs text-indigo-600 dark:text-indigo-400">
                                                    GL: {ba.account.code} —{' '}
                                                    {ba.account.name}
                                                </p>
                                            )}
                                        </div>
                                        <Badge
                                            variant={
                                                ba.is_active
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {ba.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <div className="mt-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {formatCurrency(ba.current_balance)}
                                        </p>
                                        {ba.last_reconciliation ? (
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Last reconciled:{' '}
                                                {
                                                    ba.last_reconciliation
                                                        .period_end
                                                }
                                            </p>
                                        ) : (
                                            <p className="mt-1 text-xs text-amber-500">
                                                Never reconciled
                                            </p>
                                        )}
                                    </div>
                                </Card>
                            </Link>
                        ))}
                    </div>

                    <Card title="Total Bank Balance">
                        <p className="text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                            {formatCurrency(totalBalance)}
                        </p>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Across {bankAccounts.length} account
                            {bankAccounts.length !== 1 ? 's' : ''}
                        </p>
                    </Card>
                </>
            )}

            {/* Add Account Modal */}
            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="lg"
            >
                <form onSubmit={submitCreate} className="space-y-4 p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Add Bank Account
                    </h2>

                    <div>
                        <InputLabel value="GL Account (Cash/Bank)" />
                        <SelectInput
                            value={createForm.data.account_id}
                            onChange={(e) =>
                                createForm.setData('account_id', e.target.value)
                            }
                            className="mt-1 w-full"
                        >
                            <option value="">Select GL account…</option>
                            {glAccounts.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.code} — {a.name}
                                </option>
                            ))}
                        </SelectInput>
                        <InputError message={createForm.errors.account_id} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Bank Name" />
                            <TextInput
                                className="mt-1 w-full"
                                value={createForm.data.bank_name}
                                onChange={(e) =>
                                    createForm.setData(
                                        'bank_name',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError message={createForm.errors.bank_name} />
                        </div>
                        <div>
                            <InputLabel value="Account Number" />
                            <TextInput
                                className="mt-1 w-full"
                                value={createForm.data.account_number}
                                onChange={(e) =>
                                    createForm.setData(
                                        'account_number',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={createForm.errors.account_number}
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Account Holder Name" />
                        <TextInput
                            className="mt-1 w-full"
                            value={createForm.data.account_holder_name}
                            onChange={(e) =>
                                createForm.setData(
                                    'account_holder_name',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={createForm.errors.account_holder_name}
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Opening Balance" />
                            <TextInput
                                type="number"
                                step="0.01"
                                min="0"
                                className="mt-1 w-full"
                                value={createForm.data.opening_balance}
                                onChange={(e) =>
                                    createForm.setData(
                                        'opening_balance',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={createForm.errors.opening_balance}
                            />
                        </div>
                        <div>
                            <InputLabel value="Opening Date" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full"
                                value={createForm.data.opening_date}
                                onChange={(e) =>
                                    createForm.setData(
                                        'opening_date',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
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

            {/* Transfer Modal */}
            <Modal
                show={transferring}
                onClose={() => setTransferring(false)}
                maxWidth="lg"
            >
                <form onSubmit={submitTransfer} className="space-y-4 p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Transfer Between Accounts
                    </h2>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="From Account" />
                            <SelectInput
                                value={transferForm.data.from_bank_account_id}
                                onChange={(e) =>
                                    transferForm.setData(
                                        'from_bank_account_id',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 w-full"
                            >
                                <option value="">Select…</option>
                                {bankAccounts.map((ba) => (
                                    <option key={ba.id} value={ba.id}>
                                        {ba.bank_name} (
                                        {formatCurrency(ba.current_balance)})
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError
                                message={
                                    transferForm.errors.from_bank_account_id
                                }
                            />
                        </div>
                        <div>
                            <InputLabel value="To Account" />
                            <SelectInput
                                value={transferForm.data.to_bank_account_id}
                                onChange={(e) =>
                                    transferForm.setData(
                                        'to_bank_account_id',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 w-full"
                            >
                                <option value="">Select…</option>
                                {bankAccounts.map((ba) => (
                                    <option key={ba.id} value={ba.id}>
                                        {ba.bank_name} (
                                        {formatCurrency(ba.current_balance)})
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError
                                message={transferForm.errors.to_bank_account_id}
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Amount" />
                            <TextInput
                                type="number"
                                step="0.01"
                                min="0.01"
                                className="mt-1 w-full"
                                value={transferForm.data.amount}
                                onChange={(e) =>
                                    transferForm.setData(
                                        'amount',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError message={transferForm.errors.amount} />
                        </div>
                        <div>
                            <InputLabel value="Date" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full"
                                value={transferForm.data.date}
                                onChange={(e) =>
                                    transferForm.setData('date', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Reference (optional)" />
                        <TextInput
                            className="mt-1 w-full"
                            value={transferForm.data.reference}
                            onChange={(e) =>
                                transferForm.setData(
                                    'reference',
                                    e.target.value,
                                )
                            }
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <SecondaryButton
                            type="button"
                            onClick={() => setTransferring(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={transferForm.processing}
                        >
                            Transfer
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </FinanceLayout>
    );
}
