import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformLicensesLayout from '@/Layouts/PlatformLicensesLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface LicenseRow {
    id: string;
    license_key: string;
    type: string;
    status: 'active' | 'suspended' | 'revoked' | 'expired';
    max_devices: number;
    active_devices_count: number;
    expires_at: string | null;
    business: { id: string; name: string } | null;
}

interface PaginatedLicenses {
    data: LicenseRow[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const STATUS_VARIANT = {
    active: 'success',
    suspended: 'warning',
    revoked: 'danger',
    expired: 'neutral',
} as const;

export default function LicensesIndex({
    licenses,
    filters,
    businesses,
}: {
    licenses: PaginatedLicenses;
    filters: Record<string, string>;
    businesses: Array<{ id: string; name: string }>;
}) {
    const { notify } = useBiNotification();
    const [search, setSearch] = useState(filters.search ?? '');
    const [generating, setGenerating] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        business_id: '',
        type: 'professional',
        max_devices: '3',
        expires_at: '',
        maintenance_expires_at: '',
        offline_activation_allowed: true,
        cloud_sync_enabled: false,
        notes: '',
    });

    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('platform.licenses.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const onSearchSubmit = (e: FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const openGenerate = () => {
        reset();
        setGenerating(true);
    };

    const submitGenerate = (e: FormEvent) => {
        e.preventDefault();
        post(route('platform.licenses.store'), {
            onSuccess: () => {
                setGenerating(false);
                notify('License generated.', 'success');
            },
            // Every field below renders its own error, so this is a
            // backstop rather than the main channel: if a rule is ever
            // added server-side without a matching message in this form,
            // the button still has to do something visible. Silence on
            // submit is the one outcome that leaves someone clicking.
            onError: () =>
                notify(
                    'Could not generate the licence — check the highlighted fields.',
                    'error',
                ),
        });
    };

    // The earliest date the server will accept. `expires_at` is validated
    // with `after:today`, so today itself is rejected — a licence that
    // expires today is already expired. Enforcing it in the picker means
    // the constraint is visible while choosing rather than after
    // submitting.
    const earliestExpiry = new Date(Date.now() + 86_400_000)
        .toISOString()
        .slice(0, 10);

    const columns: BiTableColumn<LicenseRow>[] = [
        {
            key: 'key',
            label: 'License key',
            render: (l) => (
                <Link
                    href={route('platform.licenses.show', l.id)}
                    className="font-mono text-sm text-indigo-600 hover:underline"
                >
                    {l.license_key}
                </Link>
            ),
        },
        {
            key: 'business',
            label: 'Business',
            render: (l) => l.business?.name ?? '—',
        },
        {
            key: 'type',
            label: 'Type',
            render: (l) => <span className="capitalize">{l.type}</span>,
        },
        {
            key: 'devices',
            label: 'Devices',
            render: (l) => `${l.active_devices_count} / ${l.max_devices}`,
        },
        {
            key: 'expires',
            label: 'Expires',
            render: (l) =>
                l.expires_at
                    ? new Date(l.expires_at).toLocaleDateString()
                    : 'Never',
        },
        {
            key: 'status',
            label: 'Status',
            align: 'right',
            render: (l) => (
                <BiBadge variant={STATUS_VARIANT[l.status]}>{l.status}</BiBadge>
            ),
        },
    ];

    return (
        <PlatformLicensesLayout title="Licenses">
            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {licenses.meta.total} total
                </p>
                <BiButton onClick={openGenerate}>Generate license</BiButton>
            </div>

            <BiDataGrid
                columns={columns}
                paginated={licenses}
                rowKey={(l) => l.id}
                emptyMessage="No licenses match these filters."
                toolbar={
                    <>
                        <form onSubmit={onSearchSubmit} className="flex gap-2">
                            <TextInput
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by key or business"
                                className="w-72"
                            />
                            <SecondaryButton type="submit">
                                Search
                            </SecondaryButton>
                        </form>

                        <SelectInput
                            value={filters.status ?? ''}
                            onChange={(e) =>
                                applyFilters({ status: e.target.value })
                            }
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="revoked">Revoked</option>
                            <option value="expired">Expired</option>
                        </SelectInput>

                        <SelectInput
                            value={filters.type ?? ''}
                            onChange={(e) =>
                                applyFilters({ type: e.target.value })
                            }
                        >
                            <option value="">All types</option>
                            <option value="starter">Starter</option>
                            <option value="professional">Professional</option>
                            <option value="enterprise">Enterprise</option>
                            <option value="lifetime">Lifetime</option>
                        </SelectInput>
                    </>
                }
            />

            <BiModal
                show={generating}
                onClose={() => setGenerating(false)}
                title="Generate license"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setGenerating(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="generate-license-form"
                            disabled={processing}
                        >
                            Generate
                        </BiButton>
                    </>
                }
            >
                <form
                    id="generate-license-form"
                    onSubmit={submitGenerate}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Business
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={data.business_id}
                            onChange={(e) =>
                                setData('business_id', e.target.value)
                            }
                        >
                            <option value="">Select a business</option>
                            {businesses.map((business) => (
                                <option key={business.id} value={business.id}>
                                    {business.name}
                                </option>
                            ))}
                        </SelectInput>
                        {errors.business_id && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.business_id}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Type
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.type}
                                onChange={(e) =>
                                    setData('type', e.target.value)
                                }
                            >
                                <option value="starter">Starter</option>
                                <option value="professional">
                                    Professional
                                </option>
                                <option value="enterprise">Enterprise</option>
                                <option value="lifetime">Lifetime</option>
                            </SelectInput>
                            <InputError
                                message={errors.type}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Max devices
                            </label>
                            <TextInput
                                type="number"
                                min={1}
                                max={1000}
                                className="mt-1 block w-full"
                                value={data.max_devices}
                                onChange={(e) =>
                                    setData('max_devices', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.max_devices}
                                className="mt-1"
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Expires at (blank = never)
                            </label>
                            <TextInput
                                type="date"
                                min={earliestExpiry}
                                className="mt-1 block w-full"
                                value={data.expires_at}
                                onChange={(e) =>
                                    setData('expires_at', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.expires_at}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Maintenance expires at
                            </label>
                            <TextInput
                                type="date"
                                className="mt-1 block w-full"
                                value={data.maintenance_expires_at}
                                onChange={(e) =>
                                    setData(
                                        'maintenance_expires_at',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={errors.maintenance_expires_at}
                                className="mt-1"
                            />
                        </div>
                    </div>

                    <div className="flex gap-6">
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <Checkbox
                                checked={data.offline_activation_allowed}
                                onChange={(e) =>
                                    setData(
                                        'offline_activation_allowed',
                                        e.target.checked,
                                    )
                                }
                            />
                            Allow offline activation
                        </label>
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <Checkbox
                                checked={data.cloud_sync_enabled}
                                onChange={(e) =>
                                    setData(
                                        'cloud_sync_enabled',
                                        e.target.checked,
                                    )
                                }
                            />
                            Cloud sync enabled
                        </label>
                    </div>
                    <InputError message={errors.offline_activation_allowed} />
                    <InputError message={errors.cloud_sync_enabled} />

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Notes (optional)
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        <InputError message={errors.notes} className="mt-1" />
                    </div>
                </form>
            </BiModal>
        </PlatformLicensesLayout>
    );
}
