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
import { formatCurrency } from '@/lib/currency';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface ScheduleRow {
    id: string;
    period_date: string;
    depreciation_amount: number;
    accumulated_depreciation: number;
    book_value: number;
    status: string;
    entry_number: string | null;
}

interface AssetDetail {
    id: string;
    asset_code: string;
    asset_name: string;
    category: string;
    acquisition_date: string;
    acquisition_cost: number;
    useful_life_months: number;
    residual_value: number;
    depreciation_method: string;
    status: string;
    book_value: string;
    accumulated_depreciation: string;
    account: { code: string; name: string };
}

interface CashAccount {
    id: string;
    code: string;
    name: string;
}

interface Props {
    asset: AssetDetail;
    schedule: ScheduleRow[];
    cashAccounts: CashAccount[];
}

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'neutral'> = {
    active: 'success',
    fully_depreciated: 'warning',
    disposed: 'neutral',
    pending: 'neutral',
    posted: 'success',
};

export default function AssetShow({ asset, schedule, cashAccounts }: Props) {
    const [disposing, setDisposing] = useState(false);

    const disposeForm = useForm({
        disposal_date: new Date().toISOString().slice(0, 10),
        proceeds: '',
        cash_account_id: cashAccounts[0]?.id ?? '',
    });

    const submitDispose = (e: FormEvent) => {
        e.preventDefault();
        disposeForm.post(route('finance.assets.dispose', asset.id), {
            onSuccess: () => setDisposing(false),
        });
    };

    const pendingCount = schedule.filter((s) => s.status === 'pending').length;

    return (
        <FinanceLayout title={asset.asset_name}>
            <div className="flex items-center gap-3">
                <Link
                    href={route('finance.assets.index')}
                    className="text-gray-400 hover:text-gray-600"
                >
                    <ArrowLeftIcon className="h-5 w-5" />
                </Link>
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {asset.asset_code} — {asset.asset_name}
                        </h3>
                        <Badge
                            variant={STATUS_VARIANT[asset.status] ?? 'neutral'}
                        >
                            {asset.status.replace('_', ' ')}
                        </Badge>
                    </div>
                    <p className="mt-0.5 text-sm capitalize text-gray-500">
                        {asset.category}
                    </p>
                </div>
                {asset.status === 'active' && (
                    <SecondaryButton
                        onClick={() => setDisposing(true)}
                        className="text-red-600"
                    >
                        Dispose Asset
                    </SecondaryButton>
                )}
            </div>

            <div className="grid grid-cols-4 gap-4">
                <Card>
                    <p className="text-xs text-gray-500">Acquisition Cost</p>
                    <p className="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                        {formatCurrency(asset.acquisition_cost)}
                    </p>
                    <p className="mt-0.5 text-xs text-gray-400">
                        {asset.acquisition_date}
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-gray-500">
                        Accumulated Depreciation
                    </p>
                    <p className="mt-1 text-lg font-bold text-red-600">
                        (
                        {formatCurrency(
                            parseFloat(asset.accumulated_depreciation),
                        )}
                        )
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-gray-500">Book Value</p>
                    <p className="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                        {formatCurrency(parseFloat(asset.book_value))}
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-gray-500">
                        Useful Life / Method
                    </p>
                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {asset.useful_life_months} months
                    </p>
                    <p className="mt-0.5 text-xs capitalize text-gray-400">
                        {asset.depreciation_method.replace('_', ' ')}
                    </p>
                </Card>
            </div>

            <Card>
                <div className="mb-3 flex items-center justify-between">
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
                        Depreciation Schedule
                    </h4>
                    {pendingCount > 0 && (
                        <span className="text-xs text-gray-500">
                            {pendingCount} pending periods
                        </span>
                    )}
                </div>
                <div className="max-h-96 overflow-y-auto">
                    <table className="min-w-full text-sm">
                        <thead className="sticky top-0 bg-white dark:bg-gray-800">
                            <tr className="border-b border-gray-100 dark:border-gray-700">
                                <th className="pb-2 text-left font-medium text-gray-500">
                                    Period
                                </th>
                                <th className="pb-2 text-right font-medium text-gray-500">
                                    Dep. Amount
                                </th>
                                <th className="pb-2 text-right font-medium text-gray-500">
                                    Acc. Dep.
                                </th>
                                <th className="pb-2 text-right font-medium text-gray-500">
                                    Book Value
                                </th>
                                <th className="pb-2 text-left font-medium text-gray-500">
                                    Status
                                </th>
                                <th className="pb-2 text-left font-medium text-gray-500">
                                    JE #
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                            {schedule.map((row) => (
                                <tr key={row.id}>
                                    <td className="py-1.5 pr-4 font-mono text-xs text-gray-600">
                                        {row.period_date}
                                    </td>
                                    <td className="py-1.5 pr-4 text-right font-mono">
                                        {formatCurrency(
                                            row.depreciation_amount,
                                        )}
                                    </td>
                                    <td className="py-1.5 pr-4 text-right font-mono text-red-500">
                                        (
                                        {formatCurrency(
                                            row.accumulated_depreciation,
                                        )}
                                        )
                                    </td>
                                    <td className="py-1.5 pr-4 text-right font-mono">
                                        {formatCurrency(row.book_value)}
                                    </td>
                                    <td className="py-1.5 pr-4">
                                        <Badge
                                            variant={
                                                STATUS_VARIANT[row.status] ??
                                                'neutral'
                                            }
                                        >
                                            {row.status}
                                        </Badge>
                                    </td>
                                    <td className="py-1.5 text-xs text-gray-400">
                                        {row.entry_number ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            {/* Dispose Modal */}
            <Modal
                show={disposing}
                onClose={() => setDisposing(false)}
                maxWidth="md"
            >
                <form onSubmit={submitDispose} className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Dispose Asset
                    </h3>
                    <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        This will post a disposal journal entry and mark the
                        asset as disposed. Book value at disposal:{' '}
                        <strong>
                            {formatCurrency(parseFloat(asset.book_value))}
                        </strong>
                    </p>
                    <div className="space-y-4">
                        <div>
                            <InputLabel
                                htmlFor="d_date"
                                value="Disposal Date"
                            />
                            <TextInput
                                id="d_date"
                                type="date"
                                className="mt-1 block w-full"
                                value={disposeForm.data.disposal_date}
                                onChange={(e) =>
                                    disposeForm.setData(
                                        'disposal_date',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={disposeForm.errors.disposal_date}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="d_proceeds"
                                value="Sale Proceeds"
                            />
                            <TextInput
                                id="d_proceeds"
                                type="number"
                                step="0.01"
                                min="0"
                                className="mt-1 block w-full"
                                value={disposeForm.data.proceeds}
                                onChange={(e) =>
                                    disposeForm.setData(
                                        'proceeds',
                                        e.target.value,
                                    )
                                }
                                placeholder="0.00"
                                required
                            />
                            <InputError
                                message={disposeForm.errors.proceeds}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="d_cash"
                                value="Cash / Bank Account"
                            />
                            <SelectInput
                                id="d_cash"
                                className="mt-1 block w-full"
                                value={disposeForm.data.cash_account_id}
                                onChange={(e) =>
                                    disposeForm.setData(
                                        'cash_account_id',
                                        e.target.value,
                                    )
                                }
                                required
                            >
                                <option value="">Select account</option>
                                {cashAccounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.code} — {a.name}
                                    </option>
                                ))}
                            </SelectInput>
                            <InputError
                                message={disposeForm.errors.cash_account_id}
                                className="mt-1"
                            />
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setDisposing(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            disabled={disposeForm.processing}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {disposeForm.processing
                                ? 'Processing…'
                                : 'Dispose Asset'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </FinanceLayout>
    );
}
