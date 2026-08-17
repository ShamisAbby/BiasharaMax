import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import { useConfirm } from '@/Components/ConfirmDialog';
import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import CrmLayout from '@/Layouts/CrmLayout';
import {
    CampaignRecipientRow,
    CampaignStatus,
    MarketingCampaign,
} from '@/types/crm';
import { router } from '@inertiajs/react';

const STATUS_VARIANT: Record<
    CampaignStatus,
    'neutral' | 'warning' | 'success' | 'danger'
> = {
    draft: 'neutral',
    sending: 'warning',
    sent: 'success',
    failed: 'danger',
};

const RECIPIENT_VARIANT: Record<
    CampaignRecipientRow['status'],
    'neutral' | 'success' | 'danger'
> = {
    pending: 'neutral',
    sent: 'success',
    failed: 'danger',
};

export default function CampaignShow({
    campaign,
    recipients,
}: {
    campaign: MarketingCampaign;
    recipients: CampaignRecipientRow[];
}) {
    const askConfirm = useConfirm();
    const send = () => {
        askConfirm({
            title: `Send this campaign to ${campaign.audience_count} customer(s)?`,
            tone: 'warning',
            confirmLabel: 'Send',
            onConfirm: () => {
                router.post(route('crm.campaigns.send', campaign.id));
            },
        });
    };

    const destroy = () => {
        askConfirm({
            title: 'Delete this campaign?',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('crm.campaigns.destroy', campaign.id));
            },
        });
    };

    return (
        <CrmLayout title={campaign.name}>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {campaign.name}
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {campaign.subject}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant={STATUS_VARIANT[campaign.status]}>
                        {campaign.status}
                    </Badge>
                    {campaign.status === 'draft' && (
                        <>
                            <PrimaryButton onClick={send}>
                                Send Now
                            </PrimaryButton>
                            <DangerButton onClick={destroy}>
                                Delete
                            </DangerButton>
                        </>
                    )}
                </div>
            </div>

            <div className="grid gap-6 sm:grid-cols-3">
                <Card title="Audience">
                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {campaign.audience_count}
                    </p>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        customer(s) matched
                    </p>
                </Card>
                <Card title="Sent">
                    <p className="text-2xl font-bold text-emerald-600">
                        {campaign.sent_count}
                    </p>
                </Card>
                <Card title="Failed">
                    <p className="text-2xl font-bold text-rose-600">
                        {campaign.failed_count}
                    </p>
                </Card>
            </div>

            <Card title="Email Body">
                <p className="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">
                    {campaign.body}
                </p>
            </Card>

            <Card title="Recipients" description="Most recent 50 recipients">
                {recipients.length > 0 ? (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-gray-400">
                                <th className="pb-2 font-medium">Customer</th>
                                <th className="pb-2 font-medium">Email</th>
                                <th className="pb-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                            {recipients.map((recipient) => (
                                <tr key={recipient.id}>
                                    <td className="py-2.5 text-gray-900 dark:text-gray-100">
                                        {recipient.customer_name ?? '—'}
                                    </td>
                                    <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                        {recipient.email}
                                    </td>
                                    <td className="py-2.5">
                                        <Badge
                                            variant={
                                                RECIPIENT_VARIANT[
                                                    recipient.status
                                                ]
                                            }
                                        >
                                            {recipient.status}
                                        </Badge>
                                        {recipient.error_message && (
                                            <span className="ml-2 text-xs text-rose-600">
                                                {recipient.error_message}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        No recipients yet — this campaign hasn't been sent.
                    </p>
                )}
            </Card>
        </CrmLayout>
    );
}
