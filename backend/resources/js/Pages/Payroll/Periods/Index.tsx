import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PayrollLayout from '@/Layouts/PayrollLayout';
import { formatCurrency } from '@/lib/currency';
import { PlusIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface PeriodRow {
    id: string;
    period_name: string;
    period_start: string;
    period_end: string;
    status: string;
    total_gross: number;
    total_deductions: number;
    total_net: number;
    paid_at: string | null;
}

interface Props {
    periods: PeriodRow[];
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'neutral' | 'info'
> = {
    draft: 'neutral',
    processing: 'warning',
    approved: 'info',
    paid: 'success',
};

export default function PayrollPeriodsIndex({ periods }: Props) {
    const [creating, setCreating] = useState(false);
    const today = new Date();
    const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
        .toISOString()
        .slice(0, 10);
    const lastOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0)
        .toISOString()
        .slice(0, 10);

    const form = useForm({
        period_name: today.toLocaleDateString('en-US', {
            month: 'long',
            year: 'numeric',
        }),
        period_start: firstOfMonth,
        period_end: lastOfMonth,
        salary_cycle: 'monthly',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('payroll.periods.store'), {
            onSuccess: () => {
                setCreating(false);
                form.reset();
            },
        });
    };

    return (
        <PayrollLayout title="Payroll Periods">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Payroll Periods
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Create and process payroll for each period
                    </p>
                </div>
                <PrimaryButton onClick={() => setCreating(true)}>
                    <PlusIcon className="mr-1.5 h-4 w-4" />
                    New Period
                </PrimaryButton>
            </div>

            {periods.length === 0 ? (
                <Card>
                    <div className="py-16 text-center text-sm text-gray-500">
                        No payroll periods yet. Create your first period to
                        begin payroll processing.
                    </div>
                </Card>
            ) : (
                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-700">
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Period
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Date Range
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Gross
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Deductions
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Net Pay
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {periods.map((p) => (
                                    <tr key={p.id}>
                                        <td className="py-2 pr-4">
                                            <Link
                                                href={route(
                                                    'payroll.periods.show',
                                                    p.id,
                                                )}
                                                className="font-medium text-indigo-600 hover:text-indigo-800"
                                            >
                                                {p.period_name}
                                            </Link>
                                        </td>
                                        <td className="py-2 pr-4 text-xs text-gray-500">
                                            {p.period_start} → {p.period_end}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono">
                                            {formatCurrency(p.total_gross)}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono text-red-600">
                                            (
                                            {formatCurrency(p.total_deductions)}
                                            )
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono font-medium text-green-600">
                                            {formatCurrency(p.total_net)}
                                        </td>
                                        <td className="py-2">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANT[p.status] ??
                                                    'neutral'
                                                }
                                            >
                                                {p.status
                                                    .charAt(0)
                                                    .toUpperCase() +
                                                    p.status.slice(1)}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="md"
            >
                <form onSubmit={submit} className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        New Payroll Period
                    </h3>
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="pp_name" value="Period Name" />
                            <TextInput
                                id="pp_name"
                                className="mt-1 block w-full"
                                value={form.data.period_name}
                                onChange={(e) =>
                                    form.setData('period_name', e.target.value)
                                }
                                required
                            />
                            <InputError
                                message={form.errors.period_name}
                                className="mt-1"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    htmlFor="pp_start"
                                    value="Period Start"
                                />
                                <TextInput
                                    id="pp_start"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={form.data.period_start}
                                    onChange={(e) =>
                                        form.setData(
                                            'period_start',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="pp_end"
                                    value="Period End"
                                />
                                <TextInput
                                    id="pp_end"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={form.data.period_end}
                                    onChange={(e) =>
                                        form.setData(
                                            'period_end',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="pp_cycle"
                                value="Salary Cycle"
                            />
                            <SelectInput
                                id="pp_cycle"
                                className="mt-1 block w-full"
                                value={form.data.salary_cycle}
                                onChange={(e) =>
                                    form.setData('salary_cycle', e.target.value)
                                }
                            >
                                <option value="monthly">Monthly</option>
                                <option value="bi_weekly">Bi-weekly</option>
                                <option value="weekly">Weekly</option>
                            </SelectInput>
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
                            {form.processing ? 'Saving…' : 'Create Period'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </PayrollLayout>
    );
}
