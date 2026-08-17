import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { Account } from '@/types/finance';
import { useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

interface LineInput {
    account_id: string;
    debit: string;
    credit: string;
    description: string;
}

const emptyLine: LineInput = {
    account_id: '',
    debit: '0',
    credit: '0',
    description: '',
};

export default function JournalCreate({ accounts }: { accounts: Account[] }) {
    const { data, setData, post, processing, errors } = useForm({
        entry_date: new Date().toISOString().slice(0, 10),
        description: '',
        memo: '',
        lines: [{ ...emptyLine }, { ...emptyLine }] as LineInput[],
    });

    const addLine = () => setData('lines', [...data.lines, { ...emptyLine }]);

    const updateLine = (
        index: number,
        field: keyof LineInput,
        value: string,
    ) => {
        const lines = [...data.lines];
        lines[index] = { ...lines[index], [field]: value };
        setData('lines', lines);
    };

    const removeLine = (index: number) =>
        setData(
            'lines',
            data.lines.filter((_, i) => i !== index),
        );

    const totals = useMemo(() => {
        const totalDebit = data.lines.reduce(
            (sum, line) => sum + Number(line.debit || 0),
            0,
        );
        const totalCredit = data.lines.reduce(
            (sum, line) => sum + Number(line.credit || 0),
            0,
        );

        return {
            totalDebit,
            totalCredit,
            balanced:
                Math.abs(totalDebit - totalCredit) < 0.005 && totalDebit > 0,
        };
    }, [data.lines]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('finance.journal.store'));
    };

    return (
        <FinanceLayout title="New Journal Entry">
            <form onSubmit={submit} className="space-y-6">
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Entry details
                    </h3>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Entry date" />
                            <TextInput
                                type="date"
                                className="mt-1 block w-full"
                                value={data.entry_date}
                                onChange={(e) =>
                                    setData('entry_date', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.entry_date}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Description" />
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.description}
                                className="mt-1"
                            />
                        </div>
                    </div>
                    <div className="mt-4">
                        <InputLabel value="Memo" />
                        <textarea
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            rows={2}
                            value={data.memo}
                            onChange={(e) => setData('memo', e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Lines
                        </h3>
                        <SecondaryButton type="button" onClick={addLine}>
                            Add line
                        </SecondaryButton>
                    </div>
                    <InputError message={errors.lines} className="mt-2" />

                    <div className="mt-4 space-y-3">
                        {data.lines.map((line, index) => (
                            <div
                                key={index}
                                className="grid gap-2 sm:grid-cols-6"
                            >
                                <SelectInput
                                    className="sm:col-span-2"
                                    value={line.account_id}
                                    onChange={(e) =>
                                        updateLine(
                                            index,
                                            'account_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Select account</option>
                                    {accounts.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.code} — {a.name}
                                        </option>
                                    ))}
                                </SelectInput>
                                <TextInput
                                    type="text"
                                    placeholder="Description"
                                    className="sm:col-span-2"
                                    value={line.description}
                                    onChange={(e) =>
                                        updateLine(
                                            index,
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                                <TextInput
                                    type="number"
                                    step="0.01"
                                    placeholder="Debit"
                                    value={line.debit}
                                    onChange={(e) =>
                                        updateLine(
                                            index,
                                            'debit',
                                            e.target.value,
                                        )
                                    }
                                />
                                <div className="flex gap-2">
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        placeholder="Credit"
                                        value={line.credit}
                                        onChange={(e) =>
                                            updateLine(
                                                index,
                                                'credit',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => removeLine(index)}
                                    >
                                        &times;
                                    </SecondaryButton>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 flex items-center justify-end gap-6 border-t border-gray-100 pt-4 text-sm dark:border-gray-700">
                        <span className="text-gray-500 dark:text-gray-400">
                            Total Debit:{' '}
                            <span className="font-medium text-gray-900 dark:text-gray-100">
                                {formatCurrency(totals.totalDebit)}
                            </span>
                        </span>
                        <span className="text-gray-500 dark:text-gray-400">
                            Total Credit:{' '}
                            <span className="font-medium text-gray-900 dark:text-gray-100">
                                {formatCurrency(totals.totalCredit)}
                            </span>
                        </span>
                        <span
                            className={
                                totals.balanced
                                    ? 'font-medium text-emerald-600'
                                    : 'font-medium text-red-600'
                            }
                        >
                            {totals.balanced ? 'Balanced' : 'Not balanced'}
                        </span>
                    </div>
                </div>

                <div className="flex justify-end gap-3">
                    <PrimaryButton
                        type="submit"
                        disabled={processing || !totals.balanced}
                    >
                        Save as Draft
                    </PrimaryButton>
                </div>
            </form>
        </FinanceLayout>
    );
}
