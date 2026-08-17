import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { PlusIcon } from '@heroicons/react/24/outline';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface CurrencyRow {
    id: string;
    code: string;
    name: string;
    symbol: string;
    global_rate: string;
    is_enabled: boolean;
    is_primary: boolean;
    rate_override: string | null;
    rate_as_of: string | null;
    effective_rate: string;
}

interface Props {
    currencies: CurrencyRow[];
}

export default function CurrenciesSettings({ currencies }: Props) {
    const [enabling, setEnabling] = useState<CurrencyRow | null>(null);

    const form = useForm({
        currency_id: '',
        is_primary: false,
        rate_override: '',
        rate_as_of: new Date().toISOString().slice(0, 10),
    });

    const openEnable = (c: CurrencyRow) => {
        form.setData({
            currency_id: c.id,
            is_primary: false,
            rate_override: c.rate_override ?? '',
            rate_as_of: c.rate_as_of ?? new Date().toISOString().slice(0, 10),
        });
        setEnabling(c);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('finance.settings.currencies.enable'), {
            onSuccess: () => setEnabling(null),
        });
    };

    const disable = (currencyId: string) => {
        router.delete(route('finance.settings.currencies.disable', currencyId));
    };

    const enabledCurrencies = currencies.filter((c) => c.is_enabled);
    const availableCurrencies = currencies.filter((c) => !c.is_enabled);

    return (
        <FinanceLayout title="Currency Settings">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Currency Settings
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Enable foreign currencies for multi-currency
                        transactions. Debit/credit amounts are always recorded
                        in your base currency.
                    </p>
                </div>
            </div>

            {/* Enabled Currencies */}
            <Card>
                <h4 className="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                    Enabled Currencies
                </h4>
                {enabledCurrencies.length === 0 ? (
                    <p className="py-4 text-center text-sm text-gray-400">
                        No currencies enabled. Enable a currency below to record
                        foreign-currency transactions.
                    </p>
                ) : (
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 dark:border-gray-700">
                                <th className="pb-2 text-left font-medium text-gray-500">
                                    Code
                                </th>
                                <th className="pb-2 text-left font-medium text-gray-500">
                                    Name
                                </th>
                                <th className="pb-2 text-right font-medium text-gray-500">
                                    Effective Rate
                                </th>
                                <th className="pb-2 text-right font-medium text-gray-500">
                                    Override?
                                </th>
                                <th className="pb-2 text-left font-medium text-gray-500">
                                    Status
                                </th>
                                <th className="pb-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                            {enabledCurrencies.map((c) => (
                                <tr key={c.id}>
                                    <td className="py-2 pr-4 font-mono font-semibold text-gray-800 dark:text-gray-200">
                                        {c.symbol} {c.code}
                                    </td>
                                    <td className="py-2 pr-4 text-gray-600 dark:text-gray-400">
                                        {c.name}
                                    </td>
                                    <td className="py-2 pr-4 text-right font-mono">
                                        {c.effective_rate}
                                    </td>
                                    <td className="py-2 pr-4 text-right text-xs text-gray-400">
                                        {c.rate_override
                                            ? c.rate_override
                                            : '—'}
                                    </td>
                                    <td className="py-2 pr-4">
                                        {c.is_primary ? (
                                            <Badge variant="success">
                                                Primary
                                            </Badge>
                                        ) : (
                                            <Badge variant="info">Active</Badge>
                                        )}
                                    </td>
                                    <td className="py-2 text-right">
                                        <div className="flex justify-end gap-2">
                                            <button
                                                onClick={() => openEnable(c)}
                                                className="text-xs text-indigo-600 hover:text-indigo-800"
                                            >
                                                Edit Rate
                                            </button>
                                            {!c.is_primary && (
                                                <button
                                                    onClick={() =>
                                                        disable(c.id)
                                                    }
                                                    className="text-xs text-red-500 hover:text-red-700"
                                                >
                                                    Remove
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>

            {/* Available Currencies */}
            {availableCurrencies.length > 0 && (
                <Card>
                    <h4 className="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                        Available Currencies
                    </h4>
                    <div className="grid grid-cols-3 gap-2">
                        {availableCurrencies.map((c) => (
                            <button
                                key={c.id}
                                onClick={() => openEnable(c)}
                                className="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-left text-sm hover:border-indigo-300 hover:bg-indigo-50 dark:border-gray-700 dark:hover:bg-gray-700"
                            >
                                <span>
                                    <span className="font-mono font-semibold text-gray-800 dark:text-gray-200">
                                        {c.code}
                                    </span>
                                    <span className="ml-2 text-xs text-gray-500">
                                        {c.name}
                                    </span>
                                </span>
                                <PlusIcon className="h-4 w-4 text-indigo-400" />
                            </button>
                        ))}
                    </div>
                </Card>
            )}

            {/* Enable / Edit Modal */}
            <Modal
                show={enabling !== null}
                onClose={() => setEnabling(null)}
                maxWidth="sm"
            >
                <form onSubmit={submit} className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        {enabling?.is_enabled ? 'Edit Rate' : 'Enable'} —{' '}
                        {enabling?.code} {enabling?.name}
                    </h3>
                    <div className="space-y-4">
                        <div>
                            <InputLabel
                                htmlFor="rate_override"
                                value="Exchange Rate Override (to base)"
                            />
                            <TextInput
                                id="rate_override"
                                type="number"
                                step="0.000001"
                                min="0"
                                className="mt-1 block w-full"
                                value={form.data.rate_override}
                                onChange={(e) =>
                                    form.setData(
                                        'rate_override',
                                        e.target.value,
                                    )
                                }
                                placeholder={`Global rate: ${enabling?.global_rate ?? ''}`}
                            />
                            <p className="mt-1 text-xs text-gray-400">
                                Leave blank to use the global exchange rate.
                            </p>
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="rate_as_of"
                                value="Rate As Of"
                            />
                            <TextInput
                                id="rate_as_of"
                                type="date"
                                className="mt-1 block w-full"
                                value={form.data.rate_as_of}
                                onChange={(e) =>
                                    form.setData('rate_as_of', e.target.value)
                                }
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                id="is_primary"
                                type="checkbox"
                                className="rounded border-gray-300 text-indigo-600"
                                checked={form.data.is_primary}
                                onChange={(e) =>
                                    form.setData('is_primary', e.target.checked)
                                }
                            />
                            <InputLabel
                                htmlFor="is_primary"
                                value="Set as primary currency"
                            />
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setEnabling(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={form.processing}>
                            {form.processing
                                ? 'Saving…'
                                : enabling?.is_enabled
                                  ? 'Update'
                                  : 'Enable'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </FinanceLayout>
    );
}
