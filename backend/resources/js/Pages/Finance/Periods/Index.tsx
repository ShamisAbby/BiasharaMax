import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { FinancialPeriod } from '@/types/finance';
import {
    CalendarDaysIcon,
    LockClosedIcon,
    PlusIcon,
    StarIcon,
} from '@heroicons/react/24/outline';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Props {
    groupedPeriods: Record<string, FinancialPeriod[]>;
}

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'neutral'> = {
    open: 'success',
    locked: 'warning',
    closed: 'neutral',
};

type ConfirmAction = { type: 'lock' | 'close'; period: FinancialPeriod } | null;

export default function PeriodsIndex({ groupedPeriods }: Props) {
    const [seeding, setSeeding] = useState(false);
    const [adding, setAdding] = useState(false);
    const [confirming, setConfirming] = useState<ConfirmAction>(null);

    const seedForm = useForm({ fiscal_year: String(new Date().getFullYear()) });
    const addForm = useForm({
        fiscal_year: String(new Date().getFullYear()),
        period_name: '',
        period_start: '',
        period_end: '',
    });
    const actionForm = useForm({});

    const submitSeed = (e: FormEvent) => {
        e.preventDefault();
        seedForm.post(route('finance.periods.seed-year'), {
            onSuccess: () => {
                setSeeding(false);
                seedForm.reset();
            },
        });
    };

    const submitAdd = (e: FormEvent) => {
        e.preventDefault();
        addForm.post(route('finance.periods.store'), {
            onSuccess: () => {
                setAdding(false);
                addForm.reset();
            },
        });
    };

    const confirmAndExecute = (e: FormEvent) => {
        e.preventDefault();
        if (!confirming) return;

        if (confirming.type === 'lock') {
            actionForm.patch(
                route('finance.periods.lock', confirming.period.id),
                {
                    onSuccess: () => setConfirming(null),
                },
            );
        } else {
            actionForm.post(
                route('finance.periods.close', confirming.period.id),
                {
                    onSuccess: () => setConfirming(null),
                },
            );
        }
    };

    const fiscalYears = Object.keys(groupedPeriods).sort(
        (a, b) => Number(b) - Number(a),
    );
    const isEmpty = fiscalYears.length === 0;

    return (
        <FinanceLayout title="Accounting Periods">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Accounting Periods
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Manage financial periods and year-end closing
                    </p>
                </div>
                <div className="flex gap-2">
                    <SecondaryButton onClick={() => setAdding(true)}>
                        <PlusIcon className="mr-1.5 h-4 w-4" />
                        Add Period
                    </SecondaryButton>
                    <PrimaryButton onClick={() => setSeeding(true)}>
                        <CalendarDaysIcon className="mr-1.5 h-4 w-4" />
                        Seed Year
                    </PrimaryButton>
                </div>
            </div>

            {isEmpty ? (
                <Card>
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <CalendarDaysIcon className="mb-4 h-12 w-12 text-gray-300" />
                        <h4 className="text-base font-medium text-gray-900 dark:text-white">
                            No periods yet
                        </h4>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Click "Seed Year" to automatically generate 12
                            monthly periods for a fiscal year.
                        </p>
                    </div>
                </Card>
            ) : (
                <div className="space-y-6">
                    {fiscalYears.map((year) => {
                        const periods = groupedPeriods[year];
                        return (
                            <Card key={year}>
                                <div className="mb-4 flex items-center justify-between">
                                    <h4 className="text-base font-semibold text-gray-900 dark:text-white">
                                        Fiscal Year {year}
                                    </h4>
                                    <span className="text-xs text-gray-500">
                                        {periods.length} periods
                                    </span>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-gray-100 dark:border-gray-700">
                                                <th className="pb-2 text-left font-medium text-gray-500">
                                                    Period
                                                </th>
                                                <th className="pb-2 text-left font-medium text-gray-500">
                                                    Start
                                                </th>
                                                <th className="pb-2 text-left font-medium text-gray-500">
                                                    End
                                                </th>
                                                <th className="pb-2 text-left font-medium text-gray-500">
                                                    Status
                                                </th>
                                                <th className="pb-2 text-left font-medium text-gray-500">
                                                    Locked / Closed
                                                </th>
                                                <th className="pb-2 text-right font-medium text-gray-500">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                            {periods.map((period) => (
                                                <tr
                                                    key={period.id}
                                                    className="py-2"
                                                >
                                                    <td className="py-2 pr-4">
                                                        <span className="font-medium text-gray-900 dark:text-white">
                                                            {period.period_name}
                                                        </span>
                                                        {period.is_year_end && (
                                                            <StarIcon
                                                                className="ml-1.5 inline h-3.5 w-3.5 text-amber-500"
                                                                title="Year-end period"
                                                            />
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-4 text-gray-600 dark:text-gray-400">
                                                        {period.period_start}
                                                    </td>
                                                    <td className="py-2 pr-4 text-gray-600 dark:text-gray-400">
                                                        {period.period_end}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        <Badge
                                                            variant={
                                                                STATUS_VARIANT[
                                                                    period
                                                                        .status
                                                                ]
                                                            }
                                                        >
                                                            {period.status
                                                                .charAt(0)
                                                                .toUpperCase() +
                                                                period.status.slice(
                                                                    1,
                                                                )}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-2 pr-4 text-xs text-gray-500">
                                                        {period.closed_at
                                                            ? `Closed ${period.closed_at.slice(0, 10)}`
                                                            : period.locked_at
                                                              ? `Locked ${period.locked_at.slice(0, 10)}`
                                                              : '—'}
                                                    </td>
                                                    <td className="py-2 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            {period.status ===
                                                                'open' && (
                                                                <button
                                                                    onClick={() =>
                                                                        setConfirming(
                                                                            {
                                                                                type: 'lock',
                                                                                period,
                                                                            },
                                                                        )
                                                                    }
                                                                    className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200 hover:bg-amber-50 dark:text-amber-400 dark:ring-amber-700 dark:hover:bg-amber-900/20"
                                                                >
                                                                    <LockClosedIcon className="h-3 w-3" />
                                                                    Lock
                                                                </button>
                                                            )}
                                                            {(period.status ===
                                                                'open' ||
                                                                period.status ===
                                                                    'locked') && (
                                                                <button
                                                                    onClick={() =>
                                                                        setConfirming(
                                                                            {
                                                                                type: 'close',
                                                                                period,
                                                                            },
                                                                        )
                                                                    }
                                                                    className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-200 hover:bg-red-50 dark:text-red-400 dark:ring-red-700 dark:hover:bg-red-900/20"
                                                                >
                                                                    Close
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </Card>
                        );
                    })}
                </div>
            )}

            {/* Seed Year Modal */}
            <Modal
                show={seeding}
                onClose={() => setSeeding(false)}
                maxWidth="sm"
            >
                <form onSubmit={submitSeed} className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Seed 12 Monthly Periods
                    </h3>
                    <div className="space-y-4">
                        <div>
                            <InputLabel
                                htmlFor="seed_year"
                                value="Fiscal Year"
                            />
                            <TextInput
                                id="seed_year"
                                type="number"
                                className="mt-1 block w-full"
                                value={seedForm.data.fiscal_year}
                                onChange={(e) =>
                                    seedForm.setData(
                                        'fiscal_year',
                                        e.target.value,
                                    )
                                }
                                min={2000}
                                max={2100}
                                required
                            />
                            <InputError
                                message={seedForm.errors.fiscal_year}
                                className="mt-1"
                            />
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setSeeding(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={seedForm.processing}>
                            {seedForm.processing ? 'Seeding…' : 'Seed Periods'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Add Period Modal */}
            <Modal show={adding} onClose={() => setAdding(false)} maxWidth="md">
                <form onSubmit={submitAdd} className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Add Accounting Period
                    </h3>
                    <div className="space-y-4">
                        <div>
                            <InputLabel
                                htmlFor="add_year"
                                value="Fiscal Year"
                            />
                            <TextInput
                                id="add_year"
                                type="number"
                                className="mt-1 block w-full"
                                value={addForm.data.fiscal_year}
                                onChange={(e) =>
                                    addForm.setData(
                                        'fiscal_year',
                                        e.target.value,
                                    )
                                }
                                min={2000}
                                max={2100}
                                required
                            />
                            <InputError
                                message={addForm.errors.fiscal_year}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="period_name"
                                value="Period Name"
                            />
                            <TextInput
                                id="period_name"
                                className="mt-1 block w-full"
                                value={addForm.data.period_name}
                                onChange={(e) =>
                                    addForm.setData(
                                        'period_name',
                                        e.target.value,
                                    )
                                }
                                placeholder="e.g. January 2026"
                                required
                            />
                            <InputError
                                message={addForm.errors.period_name}
                                className="mt-1"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    htmlFor="period_start"
                                    value="Start Date"
                                />
                                <TextInput
                                    id="period_start"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={addForm.data.period_start}
                                    onChange={(e) =>
                                        addForm.setData(
                                            'period_start',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={addForm.errors.period_start}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="period_end"
                                    value="End Date"
                                />
                                <TextInput
                                    id="period_end"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={addForm.data.period_end}
                                    onChange={(e) =>
                                        addForm.setData(
                                            'period_end',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={addForm.errors.period_end}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setAdding(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={addForm.processing}>
                            {addForm.processing ? 'Saving…' : 'Add Period'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Lock / Close Confirmation Modal */}
            <Modal
                show={confirming !== null}
                onClose={() => setConfirming(null)}
                maxWidth="sm"
            >
                {confirming && (
                    <form onSubmit={confirmAndExecute} className="p-6">
                        <h3 className="mb-2 text-base font-semibold text-gray-900 dark:text-white">
                            {confirming.type === 'lock'
                                ? 'Lock Period'
                                : 'Close Period'}
                        </h3>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            {confirming.type === 'lock'
                                ? `Lock "${confirming.period.period_name}"? No new journal entries can be posted to this period.`
                                : `Close "${confirming.period.period_name}"? This will post year-end closing entries and permanently close this period. This cannot be undone.`}
                        </p>
                        <InputError
                            message={
                                (actionForm.errors as Record<string, string>)
                                    .period ?? ''
                            }
                            className="mt-2"
                        />
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
                                    confirming.type === 'close'
                                        ? 'bg-red-600 hover:bg-red-700'
                                        : ''
                                }
                            >
                                {actionForm.processing
                                    ? 'Processing…'
                                    : confirming.type === 'lock'
                                      ? 'Lock Period'
                                      : 'Close Period'}
                            </PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>
        </FinanceLayout>
    );
}
