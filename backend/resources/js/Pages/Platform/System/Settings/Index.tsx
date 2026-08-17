import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import Checkbox from '@/Components/Checkbox';
import { useConfirm } from '@/Components/ConfirmDialog';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { EyeIcon, EyeSlashIcon } from '@heroicons/react/24/outline';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

type SettingsGroups = Record<string, Record<string, unknown>>;
type EnvGroups = Record<'app' | 'mail' | 'database', Record<string, string>>;

interface CurrencyRow {
    id: string;
    code: string;
    name: string;
    symbol: string;
    exchange_rate_to_base: string;
    is_base: boolean;
    is_active: boolean;
}
interface TaxRateRow {
    id: string;
    name: string;
    country_code: string | null;
    rate: string;
    is_default: boolean;
    is_active: boolean;
}

const DB_TABS = [
    'general',
    'branding',
    'localization',
    'email',
    'sms',
    'payment',
    'security',
    'storage',
    'business_defaults',
];
const ENV_TABS = ['app', 'mail', 'database'] as const;
type EnvTab = (typeof ENV_TABS)[number];

const TAB_LABELS: Record<string, string> = {
    general: 'General',
    branding: 'Branding',
    localization: 'Localization',
    email: 'Email',
    sms: 'SMS',
    payment: 'Payment',
    security: 'Security',
    storage: 'Storage',
    business_defaults: 'Business Defaults',
    app: 'Application',
    mail: 'Mail Server',
    database: 'Database',
};

// ---- Env field metadata -----------------------------------------------

type FieldMeta = {
    label: string;
    type:
        | 'text'
        | 'password'
        | 'number'
        | 'url'
        | 'email'
        | 'boolean'
        | 'select';
    options?: string[];
    placeholder?: string;
    note?: string;
};

const ENV_FIELDS: Record<EnvTab, Record<string, FieldMeta>> = {
    app: {
        name: {
            label: 'Application Name',
            type: 'text',
            placeholder: 'BiasharaMax',
        },
        env: {
            label: 'Environment',
            type: 'select',
            options: ['local', 'staging', 'production'],
        },
        debug: {
            label: 'Debug Mode',
            type: 'boolean',
            note: 'Turn off in production.',
        },
        url: {
            label: 'Application URL',
            type: 'url',
            placeholder: 'http://localhost:8000',
        },
    },
    mail: {
        mailer: {
            label: 'Mailer',
            type: 'select',
            options: ['smtp', 'log', 'array', 'mailgun', 'ses', 'postmark'],
        },
        scheme: { label: 'Scheme', type: 'select', options: ['smtp', 'smtps'] },
        host: {
            label: 'SMTP Host',
            type: 'text',
            placeholder: 'smtp.hostinger.com',
        },
        port: { label: 'SMTP Port', type: 'number', placeholder: '465' },
        username: {
            label: 'Username',
            type: 'email',
            placeholder: 'you@example.com',
        },
        password: { label: 'Password', type: 'password' },
        from_address: {
            label: 'From Address',
            type: 'email',
            placeholder: 'noreply@example.com',
        },
        from_name: {
            label: 'From Name',
            type: 'text',
            placeholder: 'BiasharaMax',
        },
    },
    database: {
        connection: {
            label: 'Driver',
            type: 'select',
            options: ['pgsql', 'mysql', 'sqlite', 'sqlsrv'],
        },
        host: { label: 'Host', type: 'text', placeholder: '127.0.0.1' },
        port: { label: 'Port', type: 'number', placeholder: '5432' },
        database: { label: 'Database Name', type: 'text' },
        username: { label: 'Username', type: 'text' },
        password: { label: 'Password', type: 'password' },
    },
};

// -----------------------------------------------------------------------

export default function PlatformSettingsIndex({
    settings,
    envSettings,
    currencies,
    taxRates,
}: {
    settings: SettingsGroups;
    envSettings: EnvGroups;
    currencies: CurrencyRow[];
    taxRates: TaxRateRow[];
    subscriptionPlans: { id: string; name: string }[];
    businessTypes: { id: string; name: string }[];
    websiteTemplates: { id: string; name: string }[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [activeTab, setActiveTab] = useState('general');
    const [values, setValues] = useState<SettingsGroups>(settings);
    const [envValues, setEnvValues] = useState<EnvGroups>(envSettings);

    useEffect(() => {
        setValues(settings);
    }, [settings]);
    useEffect(() => {
        setEnvValues(envSettings);
    }, [envSettings]);

    const setField = (group: string, key: string, value: unknown) =>
        setValues((prev) => ({
            ...prev,
            [group]: { ...prev[group], [key]: value },
        }));

    const setEnvField = (group: EnvTab, key: string, value: string) =>
        setEnvValues((prev) => ({
            ...prev,
            [group]: { ...prev[group], [key]: value },
        }));

    const saveGroup = (group: string) => {
        router.patch(
            route('platform.system.settings.update', group),
            values[group] as Record<
                string,
                string | number | boolean | string[]
            >,
            {
                onSuccess: () =>
                    notify(`${TAB_LABELS[group]} settings saved.`, 'success'),
            },
        );
    };

    const saveEnvGroup = (group: EnvTab) => {
        router.patch(
            route('platform.system.settings.env.update', group),
            envValues[group] as Record<string, string>,
            {
                onSuccess: () =>
                    notify(`${TAB_LABELS[group]} settings saved.`, 'success'),
                onError: () => notify('Failed to save settings.', 'error'),
            },
        );
    };

    const currencyForm = useForm({
        code: '',
        name: '',
        symbol: '',
        exchange_rate_to_base: '1',
    });
    const taxRateForm = useForm({
        name: '',
        country_code: '',
        rate: '0',
        is_default: false,
    });

    const submitCurrency = (e: FormEvent) => {
        e.preventDefault();
        currencyForm.post(route('platform.system.settings.currencies.store'), {
            onSuccess: () => {
                currencyForm.reset();
                notify('Currency added.', 'success');
            },
        });
    };

    const submitTaxRate = (e: FormEvent) => {
        e.preventDefault();
        taxRateForm.post(route('platform.system.settings.tax-rates.store'), {
            onSuccess: () => {
                taxRateForm.reset();
                notify('Tax rate added.', 'success');
            },
        });
    };

    const deleteTaxRate = (taxRate: TaxRateRow) => {
        askConfirm({
            title: `Delete tax rate "${taxRate.name}"?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route(
                        'platform.system.settings.tax-rates.destroy',
                        taxRate.id,
                    ),
                );
            },
        });
    };

    const isEnvTab = (tab: string): tab is EnvTab =>
        (ENV_TABS as readonly string[]).includes(tab);
    const group = values[activeTab] ?? {};

    return (
        <PlatformLayout>
            <div className="grid gap-6 lg:grid-cols-4">
                {/* --- Sidebar nav --- */}
                <BiCard title="Settings">
                    <nav className="space-y-1">
                        {/* DB-backed groups */}
                        {DB_TABS.map((tab) => (
                            <NavBtn
                                key={tab}
                                tab={tab}
                                active={activeTab}
                                label={TAB_LABELS[tab]}
                                onClick={setActiveTab}
                            />
                        ))}

                        {/* Env-backed groups */}
                        <div className="my-2 border-t border-gray-100 dark:border-gray-700" />
                        <p className="px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Server Configuration
                        </p>
                        {ENV_TABS.map((tab) => (
                            <NavBtn
                                key={tab}
                                tab={tab}
                                active={activeTab}
                                label={TAB_LABELS[tab]}
                                onClick={setActiveTab}
                            />
                        ))}

                        <div className="my-2 border-t border-gray-100 dark:border-gray-700" />
                        <NavBtn
                            tab="currencies"
                            active={activeTab}
                            label="Currencies"
                            onClick={setActiveTab}
                        />
                        <NavBtn
                            tab="tax_rates"
                            active={activeTab}
                            label="Tax Rates"
                            onClick={setActiveTab}
                        />
                    </nav>
                </BiCard>

                {/* --- Content panel --- */}
                <div className="space-y-4 lg:col-span-3">
                    {/* DB-backed tabs */}
                    {DB_TABS.includes(activeTab) && (
                        <BiCard
                            title={TAB_LABELS[activeTab]}
                            actions={
                                <BiButton onClick={() => saveGroup(activeTab)}>
                                    Save
                                </BiButton>
                            }
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                {Object.entries(group).map(([key, value]) => (
                                    <SettingField
                                        key={key}
                                        groupName={activeTab}
                                        keyName={key}
                                        value={value}
                                        onChange={(v) =>
                                            setField(activeTab, key, v)
                                        }
                                    />
                                ))}
                            </div>
                        </BiCard>
                    )}

                    {/* Env-backed tabs */}
                    {isEnvTab(activeTab) && (
                        <EnvSection
                            group={activeTab}
                            values={envValues[activeTab]}
                            onChange={(key, val) =>
                                setEnvField(activeTab, key, val)
                            }
                            onSave={() => saveEnvGroup(activeTab)}
                        />
                    )}

                    {/* Currencies */}
                    {activeTab === 'currencies' && (
                        <BiCard title="Currencies">
                            <div className="mb-4 divide-y divide-gray-100 dark:divide-gray-700">
                                {currencies.map((currency) => (
                                    <div
                                        key={currency.id}
                                        className="flex items-center justify-between py-2 text-sm"
                                    >
                                        <span className="text-gray-900 dark:text-gray-100">
                                            {currency.code} — {currency.name} (
                                            {currency.symbol})
                                        </span>
                                        <div className="flex items-center gap-2">
                                            {currency.is_base && (
                                                <BiBadge variant="info">
                                                    Base
                                                </BiBadge>
                                            )}
                                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                                Rate:{' '}
                                                {currency.exchange_rate_to_base}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <form
                                onSubmit={submitCurrency}
                                className="grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 dark:border-gray-700 sm:grid-cols-4"
                            >
                                <TextInput
                                    placeholder="Code"
                                    value={currencyForm.data.code}
                                    onChange={(e) =>
                                        currencyForm.setData(
                                            'code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                />
                                <TextInput
                                    placeholder="Name"
                                    value={currencyForm.data.name}
                                    onChange={(e) =>
                                        currencyForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                />
                                <TextInput
                                    placeholder="Symbol"
                                    value={currencyForm.data.symbol}
                                    onChange={(e) =>
                                        currencyForm.setData(
                                            'symbol',
                                            e.target.value,
                                        )
                                    }
                                />
                                <TextInput
                                    placeholder="Rate to base"
                                    type="number"
                                    value={
                                        currencyForm.data.exchange_rate_to_base
                                    }
                                    onChange={(e) =>
                                        currencyForm.setData(
                                            'exchange_rate_to_base',
                                            e.target.value,
                                        )
                                    }
                                />
                                <BiButton
                                    type="submit"
                                    className="col-span-2 sm:col-span-4"
                                >
                                    Add currency
                                </BiButton>
                            </form>
                        </BiCard>
                    )}

                    {/* Tax Rates */}
                    {activeTab === 'tax_rates' && (
                        <BiCard title="Tax Rates">
                            <div className="mb-4 divide-y divide-gray-100 dark:divide-gray-700">
                                {taxRates.map((taxRate) => (
                                    <div
                                        key={taxRate.id}
                                        className="flex items-center justify-between py-2 text-sm"
                                    >
                                        <span className="text-gray-900 dark:text-gray-100">
                                            {taxRate.name} — {taxRate.rate}%{' '}
                                            {taxRate.country_code
                                                ? `(${taxRate.country_code})`
                                                : ''}
                                        </span>
                                        <div className="flex items-center gap-3">
                                            {taxRate.is_default && (
                                                <BiBadge variant="info">
                                                    Default
                                                </BiBadge>
                                            )}
                                            <button
                                                onClick={() =>
                                                    deleteTaxRate(taxRate)
                                                }
                                                className="text-sm text-red-600 hover:underline"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <form
                                onSubmit={submitTaxRate}
                                className="grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 dark:border-gray-700 sm:grid-cols-4"
                            >
                                <TextInput
                                    placeholder="Name"
                                    value={taxRateForm.data.name}
                                    onChange={(e) =>
                                        taxRateForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                />
                                <TextInput
                                    placeholder="Country code"
                                    value={taxRateForm.data.country_code}
                                    onChange={(e) =>
                                        taxRateForm.setData(
                                            'country_code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                />
                                <TextInput
                                    placeholder="Rate %"
                                    type="number"
                                    value={taxRateForm.data.rate}
                                    onChange={(e) =>
                                        taxRateForm.setData(
                                            'rate',
                                            e.target.value,
                                        )
                                    }
                                />
                                <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <Checkbox
                                        checked={taxRateForm.data.is_default}
                                        onChange={(e) =>
                                            taxRateForm.setData(
                                                'is_default',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Default
                                </label>
                                <BiButton
                                    type="submit"
                                    className="col-span-2 sm:col-span-4"
                                >
                                    Add tax rate
                                </BiButton>
                            </form>
                        </BiCard>
                    )}
                </div>
            </div>
        </PlatformLayout>
    );
}

// -----------------------------------------------------------------------
// Nav button helper
// -----------------------------------------------------------------------

function NavBtn({
    tab,
    active,
    label,
    onClick,
}: {
    tab: string;
    active: string;
    label: string;
    onClick: (t: string) => void;
}) {
    return (
        <button
            onClick={() => onClick(tab)}
            className={`block w-full rounded-md px-3 py-2 text-left text-sm transition-colors ${
                active === tab
                    ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                    : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'
            }`}
        >
            {label}
        </button>
    );
}

// -----------------------------------------------------------------------
// Env settings section — rendered for app / mail / database tabs
// -----------------------------------------------------------------------

function EnvSection({
    group,
    values,
    onChange,
    onSave,
}: {
    group: EnvTab;
    values: Record<string, string>;
    onChange: (key: string, value: string) => void;
    onSave: () => void;
}) {
    const fields = ENV_FIELDS[group];
    const isDatabaseGroup = group === 'database';

    return (
        <>
            {isDatabaseGroup && (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800/40 dark:bg-amber-900/20 dark:text-amber-300">
                    <strong>Caution:</strong> Changing database credentials
                    disconnects the application from the current database. Apply
                    only if you know the new connection details are correct.
                </div>
            )}

            <BiCard
                title={TAB_LABELS[group]}
                actions={<BiButton onClick={onSave}>Save</BiButton>}
            >
                <div className="grid gap-5 sm:grid-cols-2">
                    {Object.entries(fields).map(([key, meta]) => (
                        <EnvField
                            key={key}
                            fieldKey={key}
                            meta={meta}
                            value={values[key] ?? ''}
                            onChange={(v) => onChange(key, v)}
                        />
                    ))}
                </div>
            </BiCard>
        </>
    );
}

// -----------------------------------------------------------------------
// Individual env field renderer
// -----------------------------------------------------------------------

function EnvField({
    fieldKey,
    meta,
    value,
    onChange,
}: {
    fieldKey: string;
    meta: FieldMeta;
    value: string;
    onChange: (v: string) => void;
}) {
    const [showPassword, setShowPassword] = useState(false);

    if (meta.type === 'boolean') {
        const checked = value === 'true' || value === '1';
        return (
            <div className="flex flex-col gap-1">
                <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <Checkbox
                        checked={checked}
                        onChange={(e) =>
                            onChange(e.target.checked ? 'true' : 'false')
                        }
                    />
                    {meta.label}
                </label>
                {meta.note && (
                    <p className="ml-6 text-xs text-gray-400 dark:text-gray-500">
                        {meta.note}
                    </p>
                )}
            </div>
        );
    }

    if (meta.type === 'select') {
        return (
            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {meta.label}
                </label>
                <select
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                    {meta.options?.map((opt) => (
                        <option key={opt} value={opt}>
                            {opt}
                        </option>
                    ))}
                </select>
            </div>
        );
    }

    if (meta.type === 'password') {
        return (
            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {meta.label}
                </label>
                <div className="relative mt-1">
                    <TextInput
                        type={showPassword ? 'text' : 'password'}
                        className="block w-full pr-10"
                        value={value}
                        placeholder={meta.placeholder}
                        onChange={(e) => onChange(e.target.value)}
                    />
                    <button
                        type="button"
                        tabIndex={-1}
                        onClick={() => setShowPassword((p) => !p)}
                        className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    >
                        {showPassword ? (
                            <EyeSlashIcon className="h-4 w-4" />
                        ) : (
                            <EyeIcon className="h-4 w-4" />
                        )}
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {meta.label}
            </label>
            <TextInput
                type={meta.type}
                className="mt-1 block w-full"
                value={value}
                placeholder={meta.placeholder}
                onChange={(e) => onChange(e.target.value)}
            />
        </div>
    );
}

// -----------------------------------------------------------------------
// DB-backed SettingField (unchanged from original)
// -----------------------------------------------------------------------

const FILE_FIELD_PATTERN = /(^|_)(logo|favicon|icon|watermark)(_|$)/i;
const COLOR_FIELD_PATTERN = /_color$/i;

function SettingField({
    groupName,
    keyName,
    value,
    onChange,
}: {
    groupName: string;
    keyName: string;
    value: unknown;
    onChange: (value: unknown) => void;
}) {
    const { notify } = useBiNotification();
    const [uploading, setUploading] = useState(false);
    const label = keyName.replace(/_/g, ' ');

    if (typeof value === 'boolean') {
        return (
            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <Checkbox
                    checked={value}
                    onChange={(e) => onChange(e.target.checked)}
                />
                {label}
            </label>
        );
    }

    if (Array.isArray(value)) {
        return (
            <div>
                <label className="block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">
                    {label}
                </label>
                <TextInput
                    className="mt-1 block w-full"
                    value={value.join(', ')}
                    onChange={(e) =>
                        onChange(e.target.value.split(',').map((s) => s.trim()))
                    }
                />
            </div>
        );
    }

    if (FILE_FIELD_PATTERN.test(keyName)) {
        const url = (value as string | null) ?? null;

        const handleFile = (file: File | undefined) => {
            if (!file) return;
            setUploading(true);
            const formData = new FormData();
            formData.append('file', file);
            formData.append('group', groupName);
            formData.append('key', keyName);
            router.post(route('platform.system.settings.upload'), formData, {
                forceFormData: true,
                onSuccess: () => notify(`${label} uploaded.`, 'success'),
                onError: () => notify(`Failed to upload ${label}.`, 'error'),
                onFinish: () => setUploading(false),
            });
        };

        return (
            <div>
                <label className="block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">
                    {label}
                </label>
                <div className="mt-1 flex items-center gap-3">
                    <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                        {url ? (
                            <img
                                src={url}
                                alt={label}
                                className="h-full w-full object-contain"
                            />
                        ) : (
                            <span className="text-[10px] text-gray-400">
                                No file
                            </span>
                        )}
                    </div>
                    <input
                        type="file"
                        accept="image/*"
                        disabled={uploading}
                        onChange={(e) => handleFile(e.target.files?.[0])}
                        className="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-300 dark:file:bg-indigo-900/40 dark:file:text-indigo-300"
                    />
                </div>
                {uploading && (
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Uploading…
                    </p>
                )}
            </div>
        );
    }

    if (COLOR_FIELD_PATTERN.test(keyName)) {
        const color = (value as string | null) ?? '#000000';
        return (
            <div>
                <label className="block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">
                    {label}
                </label>
                <div className="mt-1 flex items-center gap-2">
                    <input
                        type="color"
                        value={color}
                        onChange={(e) => onChange(e.target.value)}
                        className="h-9 w-12 cursor-pointer rounded-md border border-gray-300 dark:border-gray-600"
                    />
                    <TextInput
                        className="block w-full"
                        value={color}
                        onChange={(e) => onChange(e.target.value)}
                    />
                </div>
            </div>
        );
    }

    return (
        <div>
            <label className="block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">
                {label}
            </label>
            <TextInput
                className="mt-1 block w-full"
                value={(value as string | number) ?? ''}
                onChange={(e) => onChange(e.target.value)}
            />
        </div>
    );
}
