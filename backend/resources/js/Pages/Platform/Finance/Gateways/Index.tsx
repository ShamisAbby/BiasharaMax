import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformFinanceLayout from '@/Layouts/PlatformFinanceLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface GatewayRow {
    id: string;
    name: string;
    slug: string;
    provider: string;
    is_enabled: boolean;
    is_configured: boolean;
    mode: 'sandbox' | 'production';
    credential_keys: string[];
    webhook_url: string | null;
    fee_percentage: string;
    fee_fixed: string;
    health_status: 'online' | 'offline' | 'degraded' | 'unknown';
    last_health_check_at: string | null;
    transactions_count: number;
}

const HEALTH_VARIANT = {
    online: 'success',
    offline: 'danger',
    degraded: 'warning',
    unknown: 'neutral',
} as const;

export default function GatewaysIndex({
    gateways,
}: {
    gateways: GatewayRow[];
}) {
    const { notify } = useBiNotification();
    const [configuring, setConfiguring] = useState<GatewayRow | null>(null);

    const list = Array.isArray(gateways)
        ? gateways
        : (gateways as unknown as { data: GatewayRow[] }).data;

    const { data, setData, patch, processing, reset } = useForm({
        mode: 'sandbox' as 'sandbox' | 'production',
        webhook_url: '',
        fee_percentage: '0',
        fee_fixed: '0',
        credentials: {} as Record<string, string>,
    });

    const openConfigure = (gateway: GatewayRow) => {
        setData({
            mode: gateway.mode,
            webhook_url: gateway.webhook_url ?? '',
            fee_percentage: gateway.fee_percentage,
            fee_fixed: gateway.fee_fixed,
            credentials: Object.fromEntries(
                gateway.credential_keys.map((k) => [k, '']),
            ),
        });
        setConfiguring(gateway);
    };

    const submitConfigure = (e: FormEvent) => {
        e.preventDefault();
        if (!configuring) return;

        const payload = {
            name: configuring.name,
            slug: configuring.slug,
            provider: configuring.provider,
            mode: data.mode,
            webhook_url: data.webhook_url || null,
            fee_percentage: data.fee_percentage,
            fee_fixed: data.fee_fixed,
            credentials: Object.fromEntries(
                Object.entries(data.credentials).filter(([, v]) => v !== ''),
            ),
        };

        router.patch(
            route('platform.finance.gateways.update', configuring.id),
            payload,
            {
                onSuccess: () => {
                    setConfiguring(null);
                    notify('Gateway updated.', 'success');
                },
            },
        );
    };

    const toggleEnabled = (gateway: GatewayRow) => {
        if (!gateway.is_enabled && !gateway.is_configured) {
            notify('Add credentials before enabling this gateway.', 'error');
            return;
        }

        router.post(
            route(
                gateway.is_enabled
                    ? 'platform.finance.gateways.disable'
                    : 'platform.finance.gateways.enable',
                gateway.id,
            ),
            {},
            {
                onSuccess: () =>
                    notify(
                        gateway.is_enabled
                            ? 'Gateway disabled.'
                            : 'Gateway enabled.',
                        'success',
                    ),
            },
        );
    };

    const checkHealth = (gateway: GatewayRow) => {
        router.post(
            route('platform.finance.gateways.check-health', gateway.id),
            {},
            {
                onSuccess: () => notify('Health check complete.', 'success'),
            },
        );
    };

    const addCredentialField = () => {
        const key = prompt(
            'Credential key (e.g. secret_key, consumer_key, api_key):',
        );
        if (key) setData('credentials', { ...data.credentials, [key]: '' });
    };

    return (
        <PlatformFinanceLayout title="Payment Gateways">
            <div className="space-y-4">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {list.length} gateways registered. A gateway must be enabled
                    and have credentials configured before it can process
                    charges.
                </p>

                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {list.map((gateway) => (
                        <BiCard
                            key={gateway.id}
                            title={gateway.name}
                            actions={
                                <div className="flex gap-1.5">
                                    <BiBadge
                                        variant={
                                            gateway.is_enabled
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {gateway.is_enabled
                                            ? 'Enabled'
                                            : 'Disabled'}
                                    </BiBadge>
                                    <BiBadge
                                        variant={
                                            HEALTH_VARIANT[
                                                gateway.health_status
                                            ]
                                        }
                                    >
                                        {gateway.health_status}
                                    </BiBadge>
                                </div>
                            }
                        >
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {gateway.mode === 'production'
                                    ? 'Production'
                                    : 'Sandbox'}{' '}
                                ·{' '}
                                {gateway.is_configured
                                    ? 'Configured'
                                    : 'Not configured'}
                            </p>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {gateway.transactions_count} transactions ·{' '}
                                {gateway.fee_percentage}% + {gateway.fee_fixed}
                            </p>

                            <div className="mt-4 flex flex-wrap gap-3 text-sm">
                                <button
                                    onClick={() => openConfigure(gateway)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Configure
                                </button>
                                <button
                                    onClick={() => toggleEnabled(gateway)}
                                    className="text-gray-600 hover:underline dark:text-gray-300"
                                >
                                    {gateway.is_enabled ? 'Disable' : 'Enable'}
                                </button>
                                <button
                                    onClick={() => checkHealth(gateway)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Check health
                                </button>
                                <Link
                                    href={route(
                                        'platform.finance.gateways.logs',
                                        gateway.id,
                                    )}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Logs
                                </Link>
                            </div>
                        </BiCard>
                    ))}
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
                            form="gateway-configure-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="gateway-configure-form"
                    onSubmit={submitConfigure}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Mode
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.mode}
                                onChange={(e) =>
                                    setData(
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
                                Fee %
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.fee_percentage}
                                onChange={(e) =>
                                    setData('fee_percentage', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Fixed fee
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.fee_fixed}
                                onChange={(e) =>
                                    setData('fee_fixed', e.target.value)
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
                            value={data.webhook_url}
                            onChange={(e) =>
                                setData('webhook_url', e.target.value)
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
                                onClick={addCredentialField}
                                className="text-sm text-indigo-600 hover:underline"
                            >
                                Add field
                            </button>
                        </div>
                        <div className="space-y-2">
                            {Object.keys(data.credentials).map((key) => (
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
                                        value={data.credentials[key]}
                                        onChange={(e) =>
                                            setData('credentials', {
                                                ...data.credentials,
                                                [key]: e.target.value,
                                            })
                                        }
                                        placeholder="••••••••"
                                    />
                                </div>
                            ))}
                            {Object.keys(data.credentials).length === 0 && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    No credential fields yet — click "Add
                                    field".
                                </p>
                            )}
                        </div>
                    </div>
                </form>
            </BiModal>
        </PlatformFinanceLayout>
    );
}
