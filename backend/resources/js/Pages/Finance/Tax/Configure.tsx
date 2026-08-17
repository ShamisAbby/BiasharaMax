import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { PlusIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface TaxRate {
    id: string;
    name: string;
    rate: number;
    country_code: string | null;
}

interface TaxConfig {
    id: string;
    tax_rate_id: string;
    tax_type: string;
    applies_to: string;
    account_id: string;
    is_active: boolean;
    tax_rate: { name: string; rate: number };
    account: { code: string; name: string };
}

interface LiabilityAccount {
    id: string;
    code: string;
    name: string;
}

interface Props {
    taxRates: TaxRate[];
    configurations: TaxConfig[];
    liabilityAccounts: LiabilityAccount[];
}

interface ConfigRow {
    tax_rate_id: string;
    tax_type: string;
    applies_to: string;
    account_id: string;
    is_active: boolean;
}

const TAX_TYPES = ['vat', 'gst', 'sales_tax', 'income_tax', 'withholding'];
const APPLIES_TO = ['sales', 'purchases', 'both'];

export default function TaxConfigure({
    taxRates,
    configurations,
    liabilityAccounts,
}: Props) {
    const [adding, setAdding] = useState(false);

    const emptyRow: ConfigRow = {
        tax_rate_id: '',
        tax_type: 'vat',
        applies_to: 'both',
        account_id: '',
        is_active: true,
    };

    const form = useForm<{ configs: ConfigRow[] }>({
        configs: [{ ...emptyRow }],
    });

    const updateRow = (
        idx: number,
        key: keyof ConfigRow,
        value: string | boolean,
    ) => {
        const updated = [...form.data.configs];
        updated[idx] = { ...updated[idx], [key]: value };
        form.setData('configs', updated);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('finance.tax.save-configure'), {
            onSuccess: () => setAdding(false),
        });
    };

    return (
        <FinanceLayout title="Tax Configuration">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Tax Configuration
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Configure tax rates that apply to this business
                    </p>
                </div>
                <div className="flex gap-3">
                    <Link
                        href={route('finance.tax.vat-return')}
                        className="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        VAT Return →
                    </Link>
                    <Link
                        href={route('finance.tax.income-tax')}
                        className="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        Income Tax →
                    </Link>
                    <PrimaryButton onClick={() => setAdding(!adding)}>
                        <PlusIcon className="mr-1.5 h-4 w-4" />
                        Add Configuration
                    </PrimaryButton>
                </div>
            </div>

            {configurations.length > 0 && (
                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-700">
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Tax Rate
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Type
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Applies To
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        GL Account
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {configurations.map((c) => (
                                    <tr key={c.id}>
                                        <td className="py-2 pr-4 font-medium text-gray-900 dark:text-white">
                                            {c.tax_rate.name} ({c.tax_rate.rate}
                                            %)
                                        </td>
                                        <td className="py-2 pr-4 text-gray-600">
                                            {c.tax_type.toUpperCase()}
                                        </td>
                                        <td className="py-2 pr-4 capitalize text-gray-600">
                                            {c.applies_to}
                                        </td>
                                        <td className="py-2 pr-4 text-gray-600">
                                            {c.account.code} — {c.account.name}
                                        </td>
                                        <td className="py-2">
                                            <Badge
                                                variant={
                                                    c.is_active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {c.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {adding && (
                <Card>
                    <form onSubmit={submit}>
                        <h4 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">
                            New Tax Configuration
                        </h4>
                        <div className="space-y-3">
                            {form.data.configs.map((row, idx) => (
                                <div
                                    key={idx}
                                    className="grid grid-cols-12 gap-3"
                                >
                                    <div className="col-span-3">
                                        <SelectInput
                                            className="block w-full"
                                            value={row.tax_rate_id}
                                            onChange={(e) =>
                                                updateRow(
                                                    idx,
                                                    'tax_rate_id',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        >
                                            <option value="">
                                                Select tax rate
                                            </option>
                                            {taxRates.map((r) => (
                                                <option key={r.id} value={r.id}>
                                                    {r.name} ({r.rate}%)
                                                </option>
                                            ))}
                                        </SelectInput>
                                    </div>
                                    <div className="col-span-2">
                                        <SelectInput
                                            className="block w-full"
                                            value={row.tax_type}
                                            onChange={(e) =>
                                                updateRow(
                                                    idx,
                                                    'tax_type',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {TAX_TYPES.map((t) => (
                                                <option key={t} value={t}>
                                                    {t.toUpperCase()}
                                                </option>
                                            ))}
                                        </SelectInput>
                                    </div>
                                    <div className="col-span-2">
                                        <SelectInput
                                            className="block w-full"
                                            value={row.applies_to}
                                            onChange={(e) =>
                                                updateRow(
                                                    idx,
                                                    'applies_to',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {APPLIES_TO.map((a) => (
                                                <option
                                                    key={a}
                                                    value={a}
                                                    className="capitalize"
                                                >
                                                    {a}
                                                </option>
                                            ))}
                                        </SelectInput>
                                    </div>
                                    <div className="col-span-4">
                                        <SelectInput
                                            className="block w-full"
                                            value={row.account_id}
                                            onChange={(e) =>
                                                updateRow(
                                                    idx,
                                                    'account_id',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        >
                                            <option value="">
                                                Select GL account
                                            </option>
                                            {liabilityAccounts.map((a) => (
                                                <option key={a.id} value={a.id}>
                                                    {a.code} — {a.name}
                                                </option>
                                            ))}
                                        </SelectInput>
                                    </div>
                                    <div className="col-span-1 flex items-center">
                                        <label className="flex items-center gap-1 text-xs text-gray-600">
                                            <input
                                                type="checkbox"
                                                checked={row.is_active}
                                                onChange={(e) =>
                                                    updateRow(
                                                        idx,
                                                        'is_active',
                                                        e.target.checked,
                                                    )
                                                }
                                                className="rounded"
                                            />
                                            Active
                                        </label>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 flex justify-end gap-3">
                            <SecondaryButton
                                type="button"
                                onClick={() => setAdding(false)}
                            >
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            )}

            {configurations.length === 0 && !adding && (
                <Card>
                    <div className="py-12 text-center text-sm text-gray-500">
                        No tax configurations yet. Click "Add Configuration" to
                        get started.
                    </div>
                </Card>
            )}
        </FinanceLayout>
    );
}
