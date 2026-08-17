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
import { BuildingOfficeIcon, PlusIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface GlAccount {
    id: string;
    code: string;
    name: string;
}

interface AssetRow {
    id: string;
    asset_code: string;
    asset_name: string;
    category: string;
    acquisition_date: string;
    acquisition_cost: number;
    depreciation_method: string;
    status: string;
    book_value: string;
    accumulated_depreciation: string;
}

interface Props {
    assets: AssetRow[];
    assetAccounts: GlAccount[];
}

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'neutral'> = {
    active: 'success',
    fully_depreciated: 'warning',
    disposed: 'neutral',
};

const CATEGORIES = [
    'land',
    'building',
    'vehicle',
    'equipment',
    'furniture',
    'intangible',
    'other',
];
const DEP_METHODS = ['straight_line', 'declining_balance', 'none'];

export default function AssetsIndex({ assets, assetAccounts }: Props) {
    const [creating, setCreating] = useState(false);

    const form = useForm({
        asset_code: '',
        asset_name: '',
        category: 'equipment',
        acquisition_date: new Date().toISOString().slice(0, 10),
        acquisition_cost: '',
        account_id: '',
        useful_life_months: '60',
        residual_value: '0',
        depreciation_method: 'straight_line',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('finance.assets.store'), {
            onSuccess: () => {
                setCreating(false);
                form.reset();
            },
        });
    };

    return (
        <FinanceLayout title="Fixed Assets">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Fixed Assets
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Asset register with depreciation schedules
                    </p>
                </div>
                <PrimaryButton onClick={() => setCreating(true)}>
                    <PlusIcon className="mr-1.5 h-4 w-4" />
                    Add Asset
                </PrimaryButton>
            </div>

            {assets.length === 0 ? (
                <Card>
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <BuildingOfficeIcon className="mb-4 h-12 w-12 text-gray-300" />
                        <h4 className="text-base font-medium text-gray-900 dark:text-white">
                            No assets registered
                        </h4>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Add your first fixed asset to start tracking
                            depreciation.
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
                                        Code / Name
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Category
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Cost
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Acc. Dep.
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Book Value
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Method
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {assets.map((asset) => (
                                    <tr key={asset.id}>
                                        <td className="py-2 pr-4">
                                            <Link
                                                href={route(
                                                    'finance.assets.show',
                                                    asset.id,
                                                )}
                                                className="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                            >
                                                {asset.asset_code} —{' '}
                                                {asset.asset_name}
                                            </Link>
                                        </td>
                                        <td className="py-2 pr-4 capitalize text-gray-600 dark:text-gray-400">
                                            {asset.category}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono text-gray-900 dark:text-white">
                                            {formatCurrency(
                                                asset.acquisition_cost,
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono text-red-600">
                                            (
                                            {formatCurrency(
                                                parseFloat(
                                                    asset.accumulated_depreciation,
                                                ),
                                            )}
                                            )
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(
                                                parseFloat(asset.book_value),
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 text-xs capitalize text-gray-500">
                                            {asset.depreciation_method.replace(
                                                '_',
                                                ' ',
                                            )}
                                        </td>
                                        <td className="py-2">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANT[
                                                        asset.status
                                                    ] ?? 'neutral'
                                                }
                                            >
                                                {asset.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* Add Asset Modal */}
            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="lg"
            >
                <form onSubmit={submit} className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Add Fixed Asset
                    </h3>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    htmlFor="a_code"
                                    value="Asset Code"
                                />
                                <TextInput
                                    id="a_code"
                                    className="mt-1 block w-full"
                                    value={form.data.asset_code}
                                    onChange={(e) =>
                                        form.setData(
                                            'asset_code',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. VEH-001"
                                    required
                                />
                                <InputError
                                    message={form.errors.asset_code}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="a_name"
                                    value="Asset Name"
                                />
                                <TextInput
                                    id="a_name"
                                    className="mt-1 block w-full"
                                    value={form.data.asset_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'asset_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Toyota Hilux"
                                    required
                                />
                                <InputError
                                    message={form.errors.asset_name}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="a_cat" value="Category" />
                                <SelectInput
                                    id="a_cat"
                                    className="mt-1 block w-full"
                                    value={form.data.category}
                                    onChange={(e) =>
                                        form.setData('category', e.target.value)
                                    }
                                >
                                    {CATEGORIES.map((c) => (
                                        <option
                                            key={c}
                                            value={c}
                                            className="capitalize"
                                        >
                                            {c}
                                        </option>
                                    ))}
                                </SelectInput>
                                <InputError
                                    message={form.errors.category}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="a_date"
                                    value="Acquisition Date"
                                />
                                <TextInput
                                    id="a_date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={form.data.acquisition_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'acquisition_date',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.acquisition_date}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    htmlFor="a_cost"
                                    value="Acquisition Cost"
                                />
                                <TextInput
                                    id="a_cost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    className="mt-1 block w-full"
                                    value={form.data.acquisition_cost}
                                    onChange={(e) =>
                                        form.setData(
                                            'acquisition_cost',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.acquisition_cost}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="a_account"
                                    value="Asset GL Account"
                                />
                                <SelectInput
                                    id="a_account"
                                    className="mt-1 block w-full"
                                    value={form.data.account_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'account_id',
                                            e.target.value,
                                        )
                                    }
                                    required
                                >
                                    <option value="">Select account</option>
                                    {assetAccounts.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.code} — {a.name}
                                        </option>
                                    ))}
                                </SelectInput>
                                <InputError
                                    message={form.errors.account_id}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <InputLabel
                                    htmlFor="a_life"
                                    value="Useful Life (months)"
                                />
                                <TextInput
                                    id="a_life"
                                    type="number"
                                    min="1"
                                    className="mt-1 block w-full"
                                    value={form.data.useful_life_months}
                                    onChange={(e) =>
                                        form.setData(
                                            'useful_life_months',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.useful_life_months}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="a_residual"
                                    value="Residual Value"
                                />
                                <TextInput
                                    id="a_residual"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    className="mt-1 block w-full"
                                    value={form.data.residual_value}
                                    onChange={(e) =>
                                        form.setData(
                                            'residual_value',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.residual_value}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="a_method"
                                    value="Depreciation Method"
                                />
                                <SelectInput
                                    id="a_method"
                                    className="mt-1 block w-full"
                                    value={form.data.depreciation_method}
                                    onChange={(e) =>
                                        form.setData(
                                            'depreciation_method',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {DEP_METHODS.map((m) => (
                                        <option key={m} value={m}>
                                            {m.replace('_', ' ')}
                                        </option>
                                    ))}
                                </SelectInput>
                            </div>
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
                            {form.processing ? 'Saving…' : 'Add Asset'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </FinanceLayout>
    );
}
