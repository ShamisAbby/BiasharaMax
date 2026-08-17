import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface ChannelRow {
    id: string;
    name: string;
    channel: string;
    provider: string;
    is_enabled: boolean;
    is_configured: boolean;
    /** Why it is not configured, and what to do — null when it is. */
    configuration_hint: string | null;
    credential_keys: string[];
}

interface TemplateRow {
    id: string;
    name: string;
    channel: string;
    category: string;
    is_active: boolean;
}

interface CampaignRow {
    id: string;
    name: string;
    channel: string;
    status: string;
    audience_type: string;
    total_recipients: number;
    sent_count: number;
    failed_count: number;
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    draft: 'neutral',
    scheduled: 'info',
    sending: 'warning',
    sent: 'success',
    failed: 'danger',
    cancelled: 'neutral',
};

export default function NotificationsIndex({
    channels,
    templates,
    campaigns,
    enabledChannels,
    channelTypes,
    templateCategories,
}: {
    channels: ChannelRow[];
    templates: TemplateRow[];
    campaigns: CampaignRow[];
    /** Channel types with at least one enabled channel — see the controller. */
    enabledChannels: string[];
    channelTypes: Record<string, string>;
    templateCategories: Record<string, string>;
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [configuring, setConfiguring] = useState<ChannelRow | null>(null);
    const [creatingCampaign, setCreatingCampaign] = useState(false);
    const [creatingChannel, setCreatingChannel] = useState(false);
    const [creatingTemplate, setCreatingTemplate] = useState(false);

    const newChannelForm = useForm({
        name: '',
        channel: 'in_app',
        provider: 'database',
        sender_id: '',
    });

    const newTemplateForm = useForm({
        name: '',
        slug: '',
        channel: 'in_app',
        category: 'broadcast',
        subject: '',
        body: '',
    });

    /**
     * Why this campaign cannot send, if it cannot.
     *
     * Mirrors the server's own check, which is the authority — this is
     * only so the button can explain itself before being pressed rather
     * than after.
     */
    const submitNewChannel = (e: FormEvent) => {
        e.preventDefault();

        newChannelForm.post(
            route('platform.operations.notifications.channels.store'),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCreatingChannel(false);
                    newChannelForm.reset();
                    notify(
                        'Channel created. Enable it once configured.',
                        'success',
                    );
                },
            },
        );
    };

    const submitNewTemplate = (e: FormEvent) => {
        e.preventDefault();

        newTemplateForm.post(
            route('platform.operations.notifications.templates.store'),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCreatingTemplate(false);
                    newTemplateForm.reset();
                    notify('Template created.', 'success');
                },
            },
        );
    };

    const blockedReason = (campaign: CampaignRow): string | null =>
        enabledChannels.includes(campaign.channel)
            ? null
            : `No enabled ${campaign.channel} channel is configured.`;

    const channelForm = useForm({
        provider: '',
        sender_id: '',
        webhook_url: '',
        credentials: {} as Record<string, string>,
    });
    const campaignForm = useForm({
        name: '',
        channel: 'email',
        subject: '',
        body: '',
        audience_type: 'all_businesses',
    });

    const openConfigure = (channel: ChannelRow) => {
        channelForm.setData({
            provider: channel.provider,
            sender_id: '',
            webhook_url: '',
            credentials: Object.fromEntries(
                channel.credential_keys.map((k) => [k, '']),
            ),
        });
        setConfiguring(channel);
    };

    const submitConfigure = (e: FormEvent) => {
        e.preventDefault();
        if (!configuring) return;

        router.patch(
            route(
                'platform.operations.notifications.channels.update',
                configuring.id,
            ),
            {
                name: configuring.name,
                channel: configuring.channel,
                provider: channelForm.data.provider,
                sender_id: channelForm.data.sender_id,
                webhook_url: channelForm.data.webhook_url,
                credentials: Object.fromEntries(
                    Object.entries(channelForm.data.credentials).filter(
                        ([, v]) => v !== '',
                    ),
                ),
            },
            {
                onSuccess: () => {
                    setConfiguring(null);
                    notify('Channel updated.', 'success');
                },
            },
        );
    };

    const toggleChannel = (channel: ChannelRow) => {
        if (!channel.is_enabled && !channel.is_configured) {
            notify('Add credentials before enabling this channel.', 'error');
            return;
        }

        router.post(
            route(
                channel.is_enabled
                    ? 'platform.operations.notifications.channels.disable'
                    : 'platform.operations.notifications.channels.enable',
                channel.id,
            ),
            {},
            {
                onSuccess: () =>
                    notify(
                        channel.is_enabled
                            ? 'Channel disabled.'
                            : 'Channel enabled.',
                        'success',
                    ),
            },
        );
    };

    const addCredentialField = () => {
        const key = prompt('Credential key (e.g. api_key, access_token):');
        if (key)
            channelForm.setData('credentials', {
                ...channelForm.data.credentials,
                [key]: '',
            });
    };

    const submitCampaign = (e: FormEvent) => {
        e.preventDefault();

        campaignForm.post(
            route('platform.operations.notifications.campaigns.store'),
            {
                onSuccess: () => {
                    setCreatingCampaign(false);
                    campaignForm.reset();
                    notify('Campaign created.', 'success');
                },
            },
        );
    };

    const sendCampaign = (campaign: CampaignRow) => {
        askConfirm({
            title: `Send "${campaign.name}" now?`,
            tone: 'warning',
            confirmLabel: 'Send',
            onConfirm: () => {
                router.post(
                    route(
                        'platform.operations.notifications.campaigns.send',
                        campaign.id,
                    ),
                    {},
                    {
                        onSuccess: () => notify('Campaign sent.', 'success'),
                    },
                );
            },
        });
    };

    return (
        <PlatformLayout>
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Notification Center
                </h1>

                <BiCard
                    title="Channels"
                    description="How platform messages actually leave the system. A campaign can only send through an enabled channel of its type."
                    actions={
                        <BiButton onClick={() => setCreatingChannel(true)}>
                            New channel
                        </BiButton>
                    }
                >
                    {/*
                      This card had no empty state at all, so a fresh
                      installation — which has no channels — rendered a
                      blank band. It looked broken rather than unconfigured,
                      and gave no hint that the missing channel is why every
                      campaign fails.
                    */}
                    {channels.length === 0 && (
                        <div className="py-8 text-center">
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                No channels configured
                            </p>
                            <p className="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                                Campaigns cannot be delivered until at least one
                                channel exists and is enabled. Start with an
                                in-app channel — it needs no external provider.
                            </p>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {channels.map((channel) => (
                            <div
                                key={channel.id}
                                className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                            >
                                <div className="flex items-center justify-between">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {channel.name}
                                    </p>
                                    <BiBadge
                                        variant={
                                            channel.is_enabled
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {channel.is_enabled
                                            ? 'Enabled'
                                            : 'Disabled'}
                                    </BiBadge>
                                </div>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {channel.channel} ·{' '}
                                    {channel.is_configured
                                        ? 'Configured'
                                        : 'Not configured'}
                                </p>

                                {/*
                                  "Not configured" names the state but not
                                  the fix — and for email the fix is not
                                  even on this screen, it is in .env.
                                */}
                                {channel.configuration_hint && (
                                    <p className="mt-2 text-xs leading-relaxed text-amber-600 dark:text-amber-400">
                                        {channel.configuration_hint}
                                    </p>
                                )}
                                <div className="mt-3 flex gap-3 text-sm">
                                    <button
                                        onClick={() => openConfigure(channel)}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        Configure
                                    </button>
                                    <button
                                        onClick={() => toggleChannel(channel)}
                                        className="text-gray-600 hover:underline dark:text-gray-300"
                                    >
                                        {channel.is_enabled
                                            ? 'Disable'
                                            : 'Enable'}
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </BiCard>

                <BiCard
                    title="Campaigns"
                    actions={
                        <BiButton onClick={() => setCreatingCampaign(true)}>
                            New campaign
                        </BiButton>
                    }
                >
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {campaigns.map((campaign) => (
                            <div
                                key={campaign.id}
                                className="flex items-center justify-between py-3 text-sm"
                            >
                                <div>
                                    <Link
                                        href={route(
                                            'platform.operations.notifications.campaigns.show',
                                            campaign.id,
                                        )}
                                        className="font-medium text-indigo-600 hover:underline"
                                    >
                                        {campaign.name}
                                    </Link>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {campaign.channel} ·{' '}
                                        {campaign.audience_type} ·{' '}
                                        {campaign.sent_count}/
                                        {campaign.total_recipients} sent
                                    </p>

                                    {/*
                                      "failed" on its own says something
                                      broke but not that the fix is a
                                      two-minute configuration change. The
                                      overwhelmingly common cause is a
                                      missing channel, and that is knowable
                                      here without touching delivery rows.
                                    */}
                                    {blockedReason(campaign) && (
                                        <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                            {blockedReason(campaign)}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-3">
                                    <BiBadge
                                        variant={
                                            STATUS_VARIANT[campaign.status] ??
                                            'neutral'
                                        }
                                    >
                                        {campaign.status}
                                    </BiBadge>

                                    {/*
                                      Failed campaigns can be sent again.
                                      Previously only drafts could, so a
                                      campaign that failed purely because no
                                      channel existed was unrecoverable —
                                      you had to retype the whole thing
                                      after fixing the channel.
                                    */}
                                    {(campaign.status === 'draft' ||
                                        campaign.status === 'failed') && (
                                        <button
                                            onClick={() =>
                                                sendCampaign(campaign)
                                            }
                                            disabled={
                                                blockedReason(campaign) !== null
                                            }
                                            title={
                                                blockedReason(campaign) ??
                                                undefined
                                            }
                                            className="text-emerald-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
                                        >
                                            {campaign.status === 'failed'
                                                ? 'Retry'
                                                : 'Send'}
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                        {campaigns.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No campaigns yet.
                            </p>
                        )}
                    </div>
                </BiCard>

                <BiCard
                    title="Templates"
                    actions={
                        <BiButton onClick={() => setCreatingTemplate(true)}>
                            New template
                        </BiButton>
                    }
                >
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {templates.map((template) => (
                            <div
                                key={template.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <span className="text-gray-900 dark:text-gray-100">
                                    {template.name}
                                </span>
                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                    {template.channel} · {template.category}
                                </span>
                            </div>
                        ))}
                        {templates.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No templates yet.
                            </p>
                        )}
                    </div>
                </BiCard>
            </div>

            <BiModal
                show={configuring !== null}
                onClose={() => setConfiguring(null)}
                title={`Configure ${configuring?.name ?? ''}`}
                maxWidth="xl"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setConfiguring(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton type="submit" form="channel-configure-form">
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="channel-configure-form"
                    onSubmit={submitConfigure}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Sender ID
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={channelForm.data.sender_id}
                                onChange={(e) =>
                                    channelForm.setData(
                                        'sender_id',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Webhook URL
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={channelForm.data.webhook_url}
                                onChange={(e) =>
                                    channelForm.setData(
                                        'webhook_url',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Credentials (encrypted)
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
                            {Object.keys(channelForm.data.credentials).map(
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
                                                channelForm.data.credentials[
                                                    key
                                                ]
                                            }
                                            onChange={(e) =>
                                                channelForm.setData(
                                                    'credentials',
                                                    {
                                                        ...channelForm.data
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
                        </div>
                    </div>
                </form>
            </BiModal>

            <BiModal
                show={creatingCampaign}
                onClose={() => setCreatingCampaign(false)}
                title="New campaign"
                maxWidth="xl"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreatingCampaign(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="campaign-form"
                            disabled={campaignForm.processing}
                        >
                            Create
                        </BiButton>
                    </>
                }
            >
                <form
                    id="campaign-form"
                    onSubmit={submitCampaign}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Name
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={campaignForm.data.name}
                            onChange={(e) =>
                                campaignForm.setData('name', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Channel
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={campaignForm.data.channel}
                                onChange={(e) =>
                                    campaignForm.setData(
                                        'channel',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="push">Push</option>
                            </SelectInput>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Audience
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={campaignForm.data.audience_type}
                                onChange={(e) =>
                                    campaignForm.setData(
                                        'audience_type',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="all_businesses">
                                    All businesses
                                </option>
                                <option value="business_type">
                                    By business type
                                </option>
                                <option value="subscription_plan">
                                    By subscription plan
                                </option>
                            </SelectInput>
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Subject
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={campaignForm.data.subject}
                            onChange={(e) =>
                                campaignForm.setData('subject', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Message
                        </label>
                        <textarea
                            className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                            rows={4}
                            value={campaignForm.data.body}
                            onChange={(e) =>
                                campaignForm.setData('body', e.target.value)
                            }
                        />
                    </div>
                </form>
            </BiModal>
            <BiModal
                show={creatingChannel}
                onClose={() => setCreatingChannel(false)}
                title="New channel"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreatingChannel(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="new-channel-form"
                            disabled={newChannelForm.processing}
                        >
                            Create channel
                        </BiButton>
                    </>
                }
            >
                <form
                    id="new-channel-form"
                    onSubmit={submitNewChannel}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Name
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={newChannelForm.data.name}
                            onChange={(e) =>
                                newChannelForm.setData('name', e.target.value)
                            }
                            placeholder="In-app notifications"
                        />
                        <InputError
                            message={newChannelForm.errors.name}
                            className="mt-1"
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Type
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={newChannelForm.data.channel}
                                onChange={(e) =>
                                    newChannelForm.setData(
                                        'channel',
                                        e.target.value,
                                    )
                                }
                            >
                                {Object.entries(channelTypes).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </SelectInput>
                            <InputError
                                message={newChannelForm.errors.channel}
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Provider
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={newChannelForm.data.provider}
                                onChange={(e) =>
                                    newChannelForm.setData(
                                        'provider',
                                        e.target.value,
                                    )
                                }
                                placeholder="database"
                            />
                            <InputError
                                message={newChannelForm.errors.provider}
                                className="mt-1"
                            />
                        </div>
                    </div>

                    {/*
                      Created disabled, always. A channel is not usable
                      until its provider credentials are filled in via
                      Configure, and enabling it first would let a campaign
                      pick it and fail against an unconfigured provider.
                    */}
                    <p className="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        New channels start disabled. Add any credentials under{' '}
                        <strong>Configure</strong>, then enable it. The in-app
                        channel needs no credentials.
                    </p>
                </form>
            </BiModal>

            <BiModal
                show={creatingTemplate}
                onClose={() => setCreatingTemplate(false)}
                title="New template"
                maxWidth="xl"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreatingTemplate(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="new-template-form"
                            disabled={newTemplateForm.processing}
                        >
                            Create template
                        </BiButton>
                    </>
                }
            >
                <form
                    id="new-template-form"
                    onSubmit={submitNewTemplate}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Name
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={newTemplateForm.data.name}
                                onChange={(e) =>
                                    newTemplateForm.setData(
                                        'name',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={newTemplateForm.errors.name}
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Slug
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={newTemplateForm.data.slug}
                                onChange={(e) =>
                                    newTemplateForm.setData(
                                        'slug',
                                        e.target.value,
                                    )
                                }
                                placeholder="trial-ending-soon"
                            />
                            <InputError
                                message={newTemplateForm.errors.slug}
                                className="mt-1"
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Channel
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={newTemplateForm.data.channel}
                                onChange={(e) =>
                                    newTemplateForm.setData(
                                        'channel',
                                        e.target.value,
                                    )
                                }
                            >
                                {Object.entries(channelTypes).map(
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
                                Category
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={newTemplateForm.data.category}
                                onChange={(e) =>
                                    newTemplateForm.setData(
                                        'category',
                                        e.target.value,
                                    )
                                }
                            >
                                {Object.entries(templateCategories).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </SelectInput>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Subject
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={newTemplateForm.data.subject}
                            onChange={(e) =>
                                newTemplateForm.setData(
                                    'subject',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={newTemplateForm.errors.subject}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Body
                        </label>
                        <textarea
                            rows={6}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={newTemplateForm.data.body}
                            onChange={(e) =>
                                newTemplateForm.setData('body', e.target.value)
                            }
                        />
                        <InputError
                            message={newTemplateForm.errors.body}
                            className="mt-1"
                        />
                    </div>
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
