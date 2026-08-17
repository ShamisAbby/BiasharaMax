import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface RouteRow {
    method: string;
    uri: string;
    name: string | null;
    action: string;
    middleware: string[];
}

interface MigrationRow {
    migration: string;
    batch: number | null;
    ran: boolean;
}

interface WebhookRow {
    id: string;
    name: string;
    url: string;
    events: string[];
    is_active: boolean;
    deliveries_count: number;
}

interface ApiTokenRow {
    id: string;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string;
}

export default function DeveloperCenterIndex({
    systemInfo,
    queueStatus,
    routes,
    migrations,
    webhooks,
    apiTokens,
    plainTextToken,
}: {
    systemInfo: Record<string, string | boolean>;
    queueStatus: { failed_jobs: number; connection: string };
    routes: RouteRow[];
    migrations: MigrationRow[];
    webhooks: WebhookRow[];
    apiTokens: ApiTokenRow[];
    plainTextToken: string | null;
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [routeSearch, setRouteSearch] = useState('');
    const [creatingWebhook, setCreatingWebhook] = useState(false);
    const [creatingToken, setCreatingToken] = useState(plainTextToken !== null);
    const [newToken, setNewToken] = useState<string | null>(plainTextToken);

    const webhookForm = useForm({
        name: '',
        url: '',
        events: 'business.created',
    });
    const tokenForm = useForm({ name: '' });

    const filteredRoutes = routes
        .filter(
            (r) =>
                r.uri.includes(routeSearch) ||
                (r.name ?? '').includes(routeSearch),
        )
        .slice(0, 100);

    const submitWebhook = (e: FormEvent) => {
        e.preventDefault();

        webhookForm.transform((data) => ({
            ...data,
            events: data.events.split(',').map((s) => s.trim()),
        }));
        webhookForm.post(
            route('platform.operations.developer.webhooks.store'),
            {
                onSuccess: () => {
                    setCreatingWebhook(false);
                    webhookForm.reset();
                    notify('Webhook created.', 'success');
                },
            },
        );
    };

    const deleteWebhook = (webhook: WebhookRow) => {
        askConfirm({
            title: `Delete webhook "${webhook.name}"?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route(
                        'platform.operations.developer.webhooks.destroy',
                        webhook.id,
                    ),
                );
            },
        });
    };

    const clearCache = () => {
        router.post(
            route('platform.operations.developer.cache.clear'),
            {},
            {
                onSuccess: () => notify('Cache cleared.', 'success'),
            },
        );
    };

    const submitToken = (e: FormEvent) => {
        e.preventDefault();

        tokenForm.post(route('platform.profile.api-tokens.store'), {
            onSuccess: () => tokenForm.reset(),
        });
    };

    const revokeToken = (token: ApiTokenRow) => {
        router.delete(route('platform.profile.api-tokens.destroy', token.id));
    };

    return (
        <PlatformLayout>
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Developer Center
                </h1>

                <div className="grid gap-6 lg:grid-cols-2">
                    <BiCard title="System Information">
                        <dl className="grid grid-cols-2 gap-2 text-sm">
                            {Object.entries(systemInfo).map(([key, value]) => (
                                <div
                                    key={key}
                                    className="flex justify-between border-b border-gray-100 py-1 dark:border-gray-700"
                                >
                                    <dt className="text-gray-500 dark:text-gray-400">
                                        {key.replace(/_/g, ' ')}
                                    </dt>
                                    <dd className="font-medium text-gray-900 dark:text-gray-100">
                                        {String(value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </BiCard>

                    <BiCard
                        title="Queue & Cache"
                        actions={
                            <BiButton size="sm" onClick={clearCache}>
                                Clear cache
                            </BiButton>
                        }
                    >
                        <p className="text-sm text-gray-700 dark:text-gray-300">
                            Connection: {queueStatus.connection}
                        </p>
                        <p className="text-sm text-gray-700 dark:text-gray-300">
                            Failed jobs: {queueStatus.failed_jobs}
                        </p>
                    </BiCard>
                </div>

                <BiCard
                    title="API Tokens"
                    actions={
                        <BiButton
                            size="sm"
                            onClick={() => setCreatingToken(true)}
                        >
                            New token
                        </BiButton>
                    }
                >
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {apiTokens.map((token) => (
                            <div
                                key={token.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <div>
                                    <p className="text-gray-900 dark:text-gray-100">
                                        {token.name}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Last used:{' '}
                                        {token.last_used_at
                                            ? new Date(
                                                  token.last_used_at,
                                              ).toLocaleString()
                                            : 'Never'}
                                    </p>
                                </div>
                                <button
                                    onClick={() => revokeToken(token)}
                                    className="text-red-600 hover:underline"
                                >
                                    Revoke
                                </button>
                            </div>
                        ))}
                        {apiTokens.length === 0 && (
                            <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No API tokens yet.
                            </p>
                        )}
                    </div>
                </BiCard>

                <BiCard
                    title="Webhooks"
                    actions={
                        <BiButton
                            size="sm"
                            onClick={() => setCreatingWebhook(true)}
                        >
                            New webhook
                        </BiButton>
                    }
                >
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {webhooks.map((webhook) => (
                            <div
                                key={webhook.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <div>
                                    <p className="text-gray-900 dark:text-gray-100">
                                        {webhook.name}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {webhook.url} ·{' '}
                                        {webhook.events.join(', ')}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <BiBadge
                                        variant={
                                            webhook.is_active
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {webhook.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </BiBadge>
                                    <Link
                                        href={route(
                                            'platform.operations.developer.webhooks.deliveries',
                                            webhook.id,
                                        )}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        Logs ({webhook.deliveries_count})
                                    </Link>
                                    <button
                                        onClick={() => deleteWebhook(webhook)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        ))}
                        {webhooks.length === 0 && (
                            <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No webhooks yet.
                            </p>
                        )}
                    </div>
                </BiCard>

                <BiCard title="Migration Status">
                    <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                        {migrations.filter((m) => m.ran).length} /{' '}
                        {migrations.length} migrations ran.
                    </p>
                    <div className="max-h-48 overflow-y-auto text-sm">
                        {migrations
                            .filter((m) => !m.ran)
                            .map((m) => (
                                <div
                                    key={m.migration}
                                    className="flex items-center justify-between border-b border-gray-100 py-1 dark:border-gray-700"
                                >
                                    <span className="text-gray-700 dark:text-gray-300">
                                        {m.migration}
                                    </span>
                                    <BiBadge variant="warning">Pending</BiBadge>
                                </div>
                            ))}
                        {migrations.every((m) => m.ran) && (
                            <p className="text-gray-500 dark:text-gray-400">
                                All migrations are up to date.
                            </p>
                        )}
                    </div>
                </BiCard>

                <BiCard title="Route List">
                    <TextInput
                        value={routeSearch}
                        onChange={(e) => setRouteSearch(e.target.value)}
                        placeholder="Filter by URI or name"
                        className="mb-3 w-full max-w-sm"
                    />
                    <div className="max-h-96 overflow-y-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th className="py-1 pr-3">Method</th>
                                    <th className="py-1 pr-3">URI</th>
                                    <th className="py-1">Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredRoutes.map((r) => (
                                    <tr
                                        key={`${r.method}-${r.uri}`}
                                        className="border-t border-gray-100 dark:border-gray-700"
                                    >
                                        <td className="py-1 pr-3 font-mono text-xs text-gray-500 dark:text-gray-400">
                                            {r.method}
                                        </td>
                                        <td className="py-1 pr-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                            {r.uri}
                                        </td>
                                        <td className="py-1 text-xs text-gray-500 dark:text-gray-400">
                                            {r.name}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <p className="mt-2 text-xs text-gray-400">
                            Showing {filteredRoutes.length} of {routes.length}{' '}
                            routes.
                        </p>
                    </div>
                </BiCard>
            </div>

            <BiModal
                show={creatingWebhook}
                onClose={() => setCreatingWebhook(false)}
                title="New webhook"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreatingWebhook(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton type="submit" form="webhook-form">
                            Create
                        </BiButton>
                    </>
                }
            >
                <form
                    id="webhook-form"
                    onSubmit={submitWebhook}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Name
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={webhookForm.data.name}
                            onChange={(e) =>
                                webhookForm.setData('name', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            URL
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={webhookForm.data.url}
                            onChange={(e) =>
                                webhookForm.setData('url', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Events (comma-separated)
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={webhookForm.data.events}
                            onChange={(e) =>
                                webhookForm.setData('events', e.target.value)
                            }
                        />
                    </div>
                </form>
            </BiModal>

            <BiModal
                show={creatingToken}
                onClose={() => {
                    setCreatingToken(false);
                    setNewToken(null);
                }}
                title="New API token"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreatingToken(false)}
                        >
                            Close
                        </SecondaryButton>
                        {!newToken && (
                            <BiButton type="submit" form="token-form">
                                Create
                            </BiButton>
                        )}
                    </>
                }
            >
                {newToken ? (
                    <div>
                        <p className="mb-2 text-sm text-gray-700 dark:text-gray-300">
                            Copy this token now — it won't be shown again.
                        </p>
                        <code className="block break-all rounded bg-gray-900 p-3 text-xs text-emerald-300">
                            {newToken}
                        </code>
                    </div>
                ) : (
                    <form id="token-form" onSubmit={submitToken}>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Token name
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={tokenForm.data.name}
                            onChange={(e) =>
                                tokenForm.setData('name', e.target.value)
                            }
                        />
                    </form>
                )}
            </BiModal>
        </PlatformLayout>
    );
}
