import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface IntegrationRow {
    id: string;
    name: string;
    slug: string;
    category: string;
    provider: string;
    is_enabled: boolean;
    is_configured: boolean;
    mode: 'sandbox' | 'production';
    credential_keys: string[];
    webhook_url: string | null;
    last_tested_at: string | null;
    last_test_result: 'success' | 'failed' | null;
    documentation_url: string | null;
    sort_order: number;
}

const TEST_RESULT_VARIANT = {
    success: 'success',
    failed: 'danger',
} as const;

const CATEGORY_LABELS: Record<string, string> = {
    oauth: 'OAuth',
    maps: 'Maps',
    analytics: 'Analytics',
    social_login: 'Social Login',
    ai: 'AI',
    communication: 'Communication',
    automation: 'Automation',
    storage: 'Storage',
    custom: 'Custom',
};

export default function IntegrationsIndex({
    integrations,
    categories,
    filters,
}: {
    integrations: { data: IntegrationRow[] } | IntegrationRow[];
    categories: string[];
    filters: { category?: string };
}) {
    const { notify } = useBiNotification();
    const [configuring, setConfiguring] = useState<IntegrationRow | null>(null);
    const [creating, setCreating] = useState(false);

    const list = Array.isArray(integrations) ? integrations : integrations.data;

    const configureForm = useForm({
        mode: 'sandbox' as 'sandbox' | 'production',
        webhook_url: '',
        documentation_url: '',
        credentials: {} as Record<string, string>,
    });

    const createForm = useForm({
        name: '',
        slug: '',
        category: 'custom',
        provider: '',
        mode: 'sandbox' as 'sandbox' | 'production',
        webhook_url: '',
        documentation_url: '',
        credentials: {} as Record<string, string>,
    });

    const filterByCategory = (category: string) => {
        router.get(
            route('platform.system.integrations.index'),
            category ? { category } : {},
            { preserveState: true },
        );
    };

    const openConfigure = (integration: IntegrationRow) => {
        configureForm.setData({
            mode: integration.mode,
            webhook_url: integration.webhook_url ?? '',
            documentation_url: integration.documentation_url ?? '',
            credentials: Object.fromEntries(
                integration.credential_keys.map((k) => [k, '']),
            ),
        });
        setConfiguring(integration);
    };

    const addCredentialField = (
        credentials: Record<string, string>,
        setCredentials: (next: Record<string, string>) => void,
    ) => {
        const key = prompt(
            'Credential key (e.g. api_key, client_secret, access_token):',
        );
        if (key) setCredentials({ ...credentials, [key]: '' });
    };

    const submitConfigure = (e: FormEvent) => {
        e.preventDefault();
        if (!configuring) return;

        router.patch(
            route('platform.system.integrations.update', configuring.id),
            {
                name: configuring.name,
                slug: configuring.slug,
                category: configuring.category,
                provider: configuring.provider,
                mode: configureForm.data.mode,
                webhook_url: configureForm.data.webhook_url || null,
                documentation_url: configureForm.data.documentation_url || null,
                credentials: Object.fromEntries(
                    Object.entries(configureForm.data.credentials).filter(
                        ([, v]) => v !== '',
                    ),
                ),
            },
            {
                onSuccess: () => {
                    setConfiguring(null);
                    notify('Integration updated.', 'success');
                },
            },
        );
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('platform.system.integrations.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
                notify('Integration added.', 'success');
            },
        });
    };

    const toggleEnabled = (integration: IntegrationRow) => {
        if (!integration.is_enabled && !integration.is_configured) {
            notify(
                'Add credentials before enabling this integration.',
                'error',
            );
            return;
        }

        router.post(
            route(
                integration.is_enabled
                    ? 'platform.system.integrations.disable'
                    : 'platform.system.integrations.enable',
                integration.id,
            ),
            {},
            {
                onSuccess: () =>
                    notify(
                        integration.is_enabled
                            ? 'Integration disabled.'
                            : 'Integration enabled.',
                        'success',
                    ),
            },
        );
    };

    const testConnection = (integration: IntegrationRow) => {
        router.post(
            route(
                'platform.system.integrations.test-connection',
                integration.id,
            ),
            {},
            {
                onSuccess: () =>
                    notify(
                        'Connection test complete — check status badge.',
                        'success',
                    ),
            },
        );
    };

    return (
        <PlatformLayout>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Integrations
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {list.length} integrations registered. An
                            integration must be enabled and configured before it
                            can be used.
                        </p>
                    </div>
                    <BiButton onClick={() => setCreating(true)}>
                        Add Integration
                    </BiButton>
                </div>

                <div className="flex flex-wrap gap-2">
                    <button
                        onClick={() => filterByCategory('')}
                        className={`rounded-full px-3 py-1 text-xs font-medium ${!filters.category ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'}`}
                    >
                        All
                    </button>
                    {categories.map((category) => (
                        <button
                            key={category}
                            onClick={() => filterByCategory(category)}
                            className={`rounded-full px-3 py-1 text-xs font-medium ${filters.category === category ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'}`}
                        >
                            {CATEGORY_LABELS[category] ?? category}
                        </button>
                    ))}
                </div>

                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {list.map((integration) => (
                        <BiCard
                            key={integration.id}
                            title={integration.name}
                            actions={
                                <div className="flex gap-1.5">
                                    <BiBadge
                                        variant={
                                            integration.is_enabled
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {integration.is_enabled
                                            ? 'Enabled'
                                            : 'Disabled'}
                                    </BiBadge>
                                    {integration.last_test_result && (
                                        <BiBadge
                                            variant={
                                                TEST_RESULT_VARIANT[
                                                    integration.last_test_result
                                                ]
                                            }
                                        >
                                            {integration.last_test_result}
                                        </BiBadge>
                                    )}
                                </div>
                            }
                        >
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {CATEGORY_LABELS[integration.category] ??
                                    integration.category}{' '}
                                · {integration.provider}
                            </p>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {integration.mode === 'production'
                                    ? 'Production'
                                    : 'Sandbox'}{' '}
                                ·{' '}
                                {integration.is_configured
                                    ? 'Configured'
                                    : 'Not configured'}
                            </p>
                            {integration.last_tested_at && (
                                <p className="mt-1 text-xs text-gray-400">
                                    Last tested{' '}
                                    {new Date(
                                        integration.last_tested_at,
                                    ).toLocaleString()}
                                </p>
                            )}

                            <div className="mt-4 flex flex-wrap gap-3 text-sm">
                                <button
                                    onClick={() => openConfigure(integration)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Configure
                                </button>
                                <button
                                    onClick={() => toggleEnabled(integration)}
                                    className="text-gray-600 hover:underline dark:text-gray-300"
                                >
                                    {integration.is_enabled
                                        ? 'Disable'
                                        : 'Enable'}
                                </button>
                                <button
                                    onClick={() => testConnection(integration)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Test connection
                                </button>
                                <Link
                                    href={route(
                                        'platform.system.integrations.logs',
                                        integration.id,
                                    )}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Logs
                                </Link>
                                {integration.documentation_url && (
                                    <a
                                        href={integration.documentation_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-gray-500 hover:underline dark:text-gray-400"
                                    >
                                        Docs
                                    </a>
                                )}
                            </div>
                        </BiCard>
                    ))}

                    {list.length === 0 && (
                        <p className="col-span-full py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No integrations in this category yet.
                        </p>
                    )}
                </div>
            </div>

            <BiModal
                show={configuring !== null}
                onClose={() => setConfiguring(null)}
                title={`Configure ${configuring?.name ?? ''}`}
                maxWidth="2xl"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setConfiguring(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="integration-configure-form"
                            disabled={configureForm.processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="integration-configure-form"
                    onSubmit={submitConfigure}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Mode
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={configureForm.data.mode}
                                onChange={(e) =>
                                    configureForm.setData(
                                        'mode',
                                        e.target.value as
                                            | 'sandbox'
                                            | 'production',
                                    )
                                }
                            >
                                <option value="sandbox">Sandbox</option>
                                <option value="production">Production</option>
                            </SelectInput>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Documentation URL
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={configureForm.data.documentation_url}
                                onChange={(e) =>
                                    configureForm.setData(
                                        'documentation_url',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Webhook URL
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={configureForm.data.webhook_url}
                            onChange={(e) =>
                                configureForm.setData(
                                    'webhook_url',
                                    e.target.value,
                                )
                            }
                        />
                    </div>

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Credentials (encrypted at rest — leave blank to
                                keep existing value)
                            </p>
                            <button
                                type="button"
                                onClick={() =>
                                    addCredentialField(
                                        configureForm.data.credentials,
                                        (next) =>
                                            configureForm.setData(
                                                'credentials',
                                                next,
                                            ),
                                    )
                                }
                                className="text-sm text-indigo-600 hover:underline"
                            >
                                Add field
                            </button>
                        </div>
                        <div className="space-y-2">
                            {Object.keys(configureForm.data.credentials).map(
                                (key) => (
                                    <div
                                        key={key}
                                        className="flex items-center gap-2"
                                    >
                                        <span className="w-40 truncate text-sm text-gray-500 dark:text-gray-400">
                                            {key}
                                        </span>
                                        <TextInput
                                            type="password"
                                            className="block w-full"
                                            value={
                                                configureForm.data.credentials[
                                                    key
                                                ]
                                            }
                                            onChange={(e) =>
                                                configureForm.setData(
                                                    'credentials',
                                                    {
                                                        ...configureForm.data
                                                            .credentials,
                                                        [key]: e.target.value,
                                                    },
                                                )
                                            }
                                            placeholder="••••••••"
                                        />
                                    </div>
                                ),
                            )}
                            {Object.keys(configureForm.data.credentials)
                                .length === 0 && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    No credential fields yet — click "Add
                                    field".
                                </p>
                            )}
                        </div>
                    </div>
                </form>
            </BiModal>

            <BiModal
                show={creating}
                onClose={() => setCreating(false)}
                title="Add Integration"
                maxWidth="2xl"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreating(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="integration-create-form"
                            disabled={createForm.processing}
                        >
                            Add
                        </BiButton>
                    </>
                }
            >
                <form
                    id="integration-create-form"
                    onSubmit={submitCreate}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Name
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Slug
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={createForm.data.slug}
                                onChange={(e) =>
                                    createForm.setData('slug', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Category
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={createForm.data.category}
                                onChange={(e) =>
                                    createForm.setData(
                                        'category',
                                        e.target.value,
                                    )
                                }
                            >
                                {Object.entries(CATEGORY_LABELS).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </SelectInput>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Provider key
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                placeholder="openai, claude, gemini, google_maps, slack…"
                                value={createForm.data.provider}
                                onChange={(e) =>
                                    createForm.setData(
                                        'provider',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Mode
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={createForm.data.mode}
                                onChange={(e) =>
                                    createForm.setData(
                                        'mode',
                                        e.target.value as
                                            | 'sandbox'
                                            | 'production',
                                    )
                                }
                            >
                                <option value="sandbox">Sandbox</option>
                                <option value="production">Production</option>
                            </SelectInput>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Documentation URL
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={createForm.data.documentation_url}
                                onChange={(e) =>
                                    createForm.setData(
                                        'documentation_url',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Webhook URL
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={createForm.data.webhook_url}
                            onChange={(e) =>
                                createForm.setData(
                                    'webhook_url',
                                    e.target.value,
                                )
                            }
                        />
                    </div>

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Credentials
                            </p>
                            <button
                                type="button"
                                onClick={() =>
                                    addCredentialField(
                                        createForm.data.credentials,
                                        (next) =>
                                            createForm.setData(
                                                'credentials',
                                                next,
                                            ),
                                    )
                                }
                                className="text-sm text-indigo-600 hover:underline"
                            >
                                Add field
                            </button>
                        </div>
                        <div className="space-y-2">
                            {Object.keys(createForm.data.credentials).map(
                                (key) => (
                                    <div
                                        key={key}
                                        className="flex items-center gap-2"
                                    >
                                        <span className="w-40 truncate text-sm text-gray-500 dark:text-gray-400">
                                            {key}
                                        </span>
                                        <TextInput
                                            type="password"
                                            className="block w-full"
                                            value={
                                                createForm.data.credentials[key]
                                            }
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'credentials',
                                                    {
                                                        ...createForm.data
                                                            .credentials,
                                                        [key]: e.target.value,
                                                    },
                                                )
                                            }
                                            placeholder="••••••••"
                                        />
                                    </div>
                                ),
                            )}
                            {Object.keys(createForm.data.credentials).length ===
                                0 && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    No credential fields yet — click "Add
                                    field".
                                </p>
                            )}
                        </div>
                    </div>
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
