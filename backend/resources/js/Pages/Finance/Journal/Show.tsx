import Badge from '@/Components/Badge';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { JournalEntry, JournalEntryStatus } from '@/types/finance';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const STATUS_VARIANT: Record<
    JournalEntryStatus,
    'neutral' | 'success' | 'warning' | 'danger'
> = {
    draft: 'warning',
    posted: 'success',
    reversed: 'neutral',
    voided: 'danger',
};

export default function JournalShow({ entry }: { entry: JournalEntry }) {
    const askConfirm = useConfirm();
    const [voiding, setVoiding] = useState(false);
    const [reversing, setReversing] = useState(false);

    const voidForm = useForm({ reason: '' });
    const reverseForm = useForm({ reason: '' });

    const post = () => {
        askConfirm({
            title: `Post entry ${entry.entry_number} to the ledger? This cannot be undone, only reversed.`,
            tone: 'warning',
            confirmLabel: 'Post',
            onConfirm: () => {
                router.post(route('finance.journal.post', entry.id));
            },
        });
    };

    const submitVoid = (e: FormEvent) => {
        e.preventDefault();
        voidForm.post(route('finance.journal.void', entry.id), {
            onSuccess: () => {
                setVoiding(false);
                voidForm.reset();
            },
        });
    };

    const submitReverse = (e: FormEvent) => {
        e.preventDefault();
        reverseForm.post(route('finance.journal.reverse', entry.id), {
            onSuccess: () => {
                setReversing(false);
                reverseForm.reset();
            },
        });
    };

    return (
        <FinanceLayout title={entry.entry_number}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        {entry.entry_number}
                    </h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {entry.description ?? 'No description'}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <Badge variant={STATUS_VARIANT[entry.status]}>
                        {entry.status}
                    </Badge>
                    {entry.status === 'draft' && (
                        <>
                            <PrimaryButton onClick={post}>Post</PrimaryButton>
                            <SecondaryButton onClick={() => setVoiding(true)}>
                                Void
                            </SecondaryButton>
                        </>
                    )}
                    {entry.status === 'posted' && (
                        <SecondaryButton onClick={() => setReversing(true)}>
                            Reverse
                        </SecondaryButton>
                    )}
                </div>
            </div>

            <div className="grid gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700 sm:grid-cols-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Entry Date
                    </p>
                    <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {entry.entry_date}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Type
                    </p>
                    <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {entry.type}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Posted By
                    </p>
                    <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {entry.posted_by?.name ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Posted At
                    </p>
                    <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {entry.posted_at ?? '—'}
                    </p>
                </div>
                {entry.memo && (
                    <div className="sm:col-span-4">
                        <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Memo
                        </p>
                        <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            {entry.memo}
                        </p>
                    </div>
                )}
                {entry.status === 'voided' && entry.void_reason && (
                    <div className="sm:col-span-4">
                        <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Void Reason
                        </p>
                        <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            {entry.void_reason}
                        </p>
                    </div>
                )}
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {['Account', 'Description', 'Debit', 'Credit'].map(
                                (h) => (
                                    <th
                                        key={h}
                                        className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        {h}
                                    </th>
                                ),
                            )}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {entry.lines?.map((line) => (
                            <tr key={line.id}>
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {line.account.code} — {line.account.name}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {line.description ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {Number(line.debit) > 0
                                        ? formatCurrency(line.debit)
                                        : ''}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {Number(line.credit) > 0
                                        ? formatCurrency(line.credit)
                                        : ''}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="border-t border-gray-200 dark:border-gray-700">
                        <tr>
                            <td
                                className="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100"
                                colSpan={2}
                            >
                                Total
                            </td>
                            <td className="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {formatCurrency(entry.total_debit)}
                            </td>
                            <td className="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {formatCurrency(entry.total_credit)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <Modal show={voiding} onClose={() => setVoiding(false)}>
                <form onSubmit={submitVoid} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Void {entry.entry_number}
                    </h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        This draft will be discarded. It was never posted to the
                        ledger.
                    </p>
                    <div className="mt-4">
                        <textarea
                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            rows={3}
                            placeholder="Reason"
                            value={voidForm.data.reason}
                            onChange={(e) =>
                                voidForm.setData('reason', e.target.value)
                            }
                        />
                        <InputError
                            message={voidForm.errors.reason}
                            className="mt-2"
                        />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setVoiding(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={voidForm.processing}
                        >
                            Void
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal show={reversing} onClose={() => setReversing(false)}>
                <form onSubmit={submitReverse} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Reverse {entry.entry_number}
                    </h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        A new, mirrored entry will be posted today with every
                        debit and credit swapped.
                    </p>
                    <div className="mt-4">
                        <textarea
                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            rows={3}
                            placeholder="Reason (optional)"
                            value={reverseForm.data.reason}
                            onChange={(e) =>
                                reverseForm.setData('reason', e.target.value)
                            }
                        />
                        <InputError
                            message={reverseForm.errors.reason}
                            className="mt-2"
                        />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setReversing(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={reverseForm.processing}
                        >
                            Reverse
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </FinanceLayout>
    );
}
