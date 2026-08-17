import Badge from '@/Components/Badge';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import CrmLayout from '@/Layouts/CrmLayout';
import {
    CampaignSegmentFilters,
    CampaignStatus,
    MarketingCampaign,
} from '@/types/crm';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

const STATUS_VARIANT: Record<
    CampaignStatus,
    'neutral' | 'warning' | 'success' | 'danger'
> = {
    draft: 'neutral',
    sending: 'warning',
    sent: 'success',
    failed: 'danger',
};

interface CampaignFormData {
    name: string;
    subject: string;
    body: string;
    tag_ids: string[];
    loyalty_tier_id: string;
    debt_status: string;
    inactive_days: string;
}

const emptyForm: CampaignFormData = {
    name: '',
    subject: '',
    body: '',
    tag_ids: [],
    loyalty_tier_id: '',
    debt_status: '',
    inactive_days: '',
};

export default function CampaignsIndex({
    campaigns,
    tags,
    tiers,
}: {
    campaigns: {
        data: MarketingCampaign[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    tags: { id: string; name: string }[];
    tiers: { id: string; name: string }[];
}) {
    const [creating, setCreating] = useState(false);
    const [audienceCount, setAudienceCount] = useState<number | null>(null);
    const createForm = useForm<CampaignFormData>(emptyForm);

    const toFilters = (data: CampaignFormData): CampaignSegmentFilters => ({
        tag_ids: data.tag_ids.length > 0 ? data.tag_ids : undefined,
        loyalty_tier_id: data.loyalty_tier_id || undefined,
        debt_status:
            (data.debt_status as 'with_debt' | 'no_debt' | '') || undefined,
        inactive_days: data.inactive_days
            ? Number(data.inactive_days)
            : undefined,
    });

    useEffect(() => {
        if (!creating) return;
        const filters = toFilters(createForm.data);
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value === undefined) return;
            if (Array.isArray(value)) {
                value.forEach((v) =>
                    params.append(`segment_filters[${key}][]`, v),
                );
            } else {
                params.append(`segment_filters[${key}]`, String(value));
            }
        });

        const timeout = setTimeout(() => {
            fetch(
                route('crm.campaigns.preview-audience') +
                    '?' +
                    params.toString(),
            )
                .then((res) => res.json())
                .then((json) => setAudienceCount(json.audience_count));
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        creating,
        createForm.data.tag_ids,
        createForm.data.loyalty_tier_id,
        createForm.data.debt_status,
        createForm.data.inactive_days,
    ]);

    const toggleTag = (tagId: string) => {
        const next = createForm.data.tag_ids.includes(tagId)
            ? createForm.data.tag_ids.filter((id) => id !== tagId)
            : [...createForm.data.tag_ids, tagId];
        createForm.setData('tag_ids', next);
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.transform((data) => ({
            name: data.name,
            subject: data.subject,
            body: data.body,
            segment_filters: toFilters(data),
        }));
        createForm.post(route('crm.campaigns.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    return (
        <CrmLayout title="Marketing Campaigns">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Campaigns
                </h3>
                <PrimaryButton onClick={() => setCreating(true)}>
                    New Campaign
                </PrimaryButton>
            </div>

            <p className="text-sm text-gray-500 dark:text-gray-400">
                Campaigns send a real email to the segment you build below.
                SMS/WhatsApp sending isn't available yet — only email is wired
                up.
            </p>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Name',
                                'Subject',
                                'Audience',
                                'Sent',
                                'Failed',
                                'Status',
                                '',
                            ].map((h) => (
                                <th
                                    key={h}
                                    className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {campaigns.data.map((campaign) => (
                            <tr
                                key={campaign.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {campaign.name}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {campaign.subject}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {campaign.audience_count}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {campaign.sent_count}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {campaign.failed_count}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={
                                            STATUS_VARIANT[campaign.status]
                                        }
                                    >
                                        {campaign.status}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <Link
                                        href={route(
                                            'crm.campaigns.show',
                                            campaign.id,
                                        )}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {campaigns.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No campaigns yet. Create one to email a customer
                        segment.
                    </p>
                )}

                {campaigns.meta.links.length > 3 && (
                    <div className="flex flex-wrap gap-1 border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                        {campaigns.meta.links.map((link, index) => (
                            <button
                                key={index}
                                disabled={!link.url}
                                onClick={() =>
                                    link.url &&
                                    router.get(
                                        link.url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                className={`rounded px-3 py-1 text-sm ${
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-gray-800'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="2xl"
            >
                <form onSubmit={submitCreate} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        New campaign
                    </h2>
                    <div className="mt-4 space-y-4">
                        <div>
                            <TextInput
                                placeholder="Campaign name (internal)"
                                className="block w-full"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                            />
                            <InputError
                                message={createForm.errors.name}
                                className="mt-2"
                            />
                        </div>
                        <div>
                            <TextInput
                                placeholder="Email subject"
                                className="block w-full"
                                value={createForm.data.subject}
                                onChange={(e) =>
                                    createForm.setData(
                                        'subject',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={createForm.errors.subject}
                                className="mt-2"
                            />
                        </div>
                        <div>
                            <textarea
                                placeholder="Email body"
                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                rows={5}
                                value={createForm.data.body}
                                onChange={(e) =>
                                    createForm.setData('body', e.target.value)
                                }
                            />
                            <InputError
                                message={createForm.errors.body}
                                className="mt-2"
                            />
                        </div>

                        <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Audience segment
                            </h3>
                            <div className="mt-3 space-y-3">
                                <div>
                                    <p className="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                        Tags
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {tags.map((tag) => {
                                            const active =
                                                createForm.data.tag_ids.includes(
                                                    tag.id,
                                                );
                                            return (
                                                <button
                                                    key={tag.id}
                                                    type="button"
                                                    onClick={() =>
                                                        toggleTag(tag.id)
                                                    }
                                                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                                        active
                                                            ? 'bg-indigo-600 text-white'
                                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                                    }`}
                                                >
                                                    {tag.name}
                                                </button>
                                            );
                                        })}
                                        {tags.length === 0 && (
                                            <p className="text-xs text-gray-400">
                                                No tags created yet.
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <SelectInput
                                    className="block w-full"
                                    value={createForm.data.loyalty_tier_id}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'loyalty_tier_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Any loyalty tier</option>
                                    {tiers.map((tier) => (
                                        <option key={tier.id} value={tier.id}>
                                            {tier.name}
                                        </option>
                                    ))}
                                </SelectInput>

                                <SelectInput
                                    className="block w-full"
                                    value={createForm.data.debt_status}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'debt_status',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Any debt status</option>
                                    <option value="with_debt">
                                        Has outstanding debt
                                    </option>
                                    <option value="no_debt">
                                        No outstanding debt
                                    </option>
                                </SelectInput>

                                <TextInput
                                    type="number"
                                    placeholder="Inactive for at least N days (optional)"
                                    className="block w-full"
                                    value={createForm.data.inactive_days}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'inactive_days',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>

                            <p className="mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                                {audienceCount === null
                                    ? 'Calculating audience…'
                                    : `${audienceCount} customer(s) match this segment`}
                            </p>
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreating(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={createForm.processing}
                        >
                            Save as Draft
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </CrmLayout>
    );
}
