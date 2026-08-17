import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PlatformLicensesLayout from '@/Layouts/PlatformLicensesLayout';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface LicenseDetail {
    id: string;
    license_key: string;
    type: string;
    status: 'active' | 'suspended' | 'revoked' | 'expired';
    max_devices: number;
    issued_at: string;
    expires_at: string | null;
    maintenance_expires_at: string | null;
    is_maintenance_active: boolean;
    offline_activation_allowed: boolean;
    cloud_sync_enabled: boolean;
    notes: string | null;
    revoked_at: string | null;
    revoked_reason: string | null;
    business: { id: string; name: string } | null;
}

interface DeviceRow {
    id: string;
    hardware_fingerprint: string;
    machine_name: string | null;
    ip_address: string | null;
    is_active: boolean;
    activated_at: string;
    last_seen_at: string | null;
    deactivated_at: string | null;
}

interface ActivationLogRow {
    id: string;
    action: string;
    result: 'success' | 'failure';
    reason: string | null;
    ip_address: string | null;
    device: {
        machine_name: string | null;
        hardware_fingerprint: string;
    } | null;
    created_at: string;
}

const STATUS_VARIANT = {
    active: 'success',
    suspended: 'warning',
    revoked: 'danger',
    expired: 'neutral',
} as const;

export default function LicenseShow({
    license,
    devices,
    activationLogs,
}: {
    license: LicenseDetail;
    devices: DeviceRow[];
    activationLogs: ActivationLogRow[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [revoking, setRevoking] = useState(false);

    const revokeForm = useForm({ reason: '' });

    const suspend = () => {
        router.post(route('platform.licenses.suspend', license.id), undefined, {
            onSuccess: () => notify('License suspended.', 'warning'),
        });
    };

    const restore = () => {
        router.post(route('platform.licenses.restore', license.id), undefined, {
            onSuccess: () => notify('License restored.', 'success'),
        });
    };

    const resetActivation = () => {
        askConfirm({
            title: 'Reset activation? This deactivates every device on this license.',
            tone: 'danger',
            confirmLabel: 'Reset',
            onConfirm: () => {
                router.post(
                    route('platform.licenses.reset-activation', license.id),
                    undefined,
                    {
                        onSuccess: () => notify('Activation reset.', 'success'),
                    },
                );
            },
        });
    };

    const deactivateDevice = (device: DeviceRow) => {
        router.post(
            route('platform.licenses.devices.deactivate', [
                license.id,
                device.id,
            ]),
            undefined,
            { onSuccess: () => notify('Device deactivated.', 'success') },
        );
    };

    const submitRevoke = (e: FormEvent) => {
        e.preventDefault();
        revokeForm.post(route('platform.licenses.revoke', license.id), {
            onSuccess: () => {
                setRevoking(false);
                notify('License revoked.', 'warning');
            },
        });
    };

    const downloadCertificate = () => {
        window.location.href = route(
            'platform.licenses.certificate',
            license.id,
        );
    };

    return (
        <PlatformLicensesLayout title={license.license_key}>
            <div className="space-y-6">
                <BiCard
                    title={license.license_key}
                    description={license.business?.name ?? undefined}
                    actions={
                        <BiBadge variant={STATUS_VARIANT[license.status]}>
                            {license.status}
                        </BiBadge>
                    }
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Type
                            </p>
                            <p className="font-medium capitalize text-gray-900 dark:text-gray-100">
                                {license.type}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Issued
                            </p>
                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                {new Date(
                                    license.issued_at,
                                ).toLocaleDateString()}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Expires
                            </p>
                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                {license.expires_at
                                    ? new Date(
                                          license.expires_at,
                                      ).toLocaleDateString()
                                    : 'Never'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Maintenance
                            </p>
                            <BiBadge
                                variant={
                                    license.is_maintenance_active
                                        ? 'success'
                                        : 'danger'
                                }
                            >
                                {license.is_maintenance_active
                                    ? 'Active'
                                    : 'Expired'}
                            </BiBadge>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Offline activation
                            </p>
                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                {license.offline_activation_allowed
                                    ? 'Allowed'
                                    : 'Not allowed'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Cloud sync
                            </p>
                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                {license.cloud_sync_enabled
                                    ? 'Enabled'
                                    : 'Disabled'}
                            </p>
                        </div>
                    </div>

                    {license.revoked_at && (
                        <p className="mt-4 text-sm text-red-600">
                            Revoked{' '}
                            {new Date(license.revoked_at).toLocaleString()} —{' '}
                            {license.revoked_reason}
                        </p>
                    )}

                    <div className="mt-6 flex flex-wrap gap-3">
                        <BiButton
                            size="sm"
                            variant="secondary"
                            onClick={downloadCertificate}
                        >
                            Download offline certificate
                        </BiButton>
                        {license.status === 'suspended' ? (
                            <BiButton size="sm" onClick={restore}>
                                Restore
                            </BiButton>
                        ) : license.status === 'active' ? (
                            <BiButton
                                size="sm"
                                variant="secondary"
                                onClick={suspend}
                            >
                                Suspend
                            </BiButton>
                        ) : null}
                        <BiButton
                            size="sm"
                            variant="secondary"
                            onClick={resetActivation}
                        >
                            Reset activation
                        </BiButton>
                        {license.status !== 'revoked' && (
                            <BiButton
                                size="sm"
                                variant="danger"
                                onClick={() => setRevoking(true)}
                            >
                                Revoke
                            </BiButton>
                        )}
                    </div>
                </BiCard>

                <BiCard
                    title="Devices"
                    description={`${devices.filter((d) => d.is_active).length} / ${license.max_devices} active`}
                    padded={false}
                >
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {devices.length === 0 && (
                            <p className="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No devices have activated this license yet.
                            </p>
                        )}
                        {devices.map((device) => (
                            <div
                                key={device.id}
                                className="flex items-center justify-between px-6 py-3 text-sm"
                            >
                                <div>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {device.machine_name ??
                                            'Unnamed device'}
                                    </p>
                                    <p className="font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {device.hardware_fingerprint}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <BiBadge
                                        variant={
                                            device.is_active
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {device.is_active
                                            ? 'Active'
                                            : 'Deactivated'}
                                    </BiBadge>
                                    {device.is_active && (
                                        <button
                                            onClick={() =>
                                                deactivateDevice(device)
                                            }
                                            className="text-red-600 hover:underline"
                                        >
                                            Deactivate
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </BiCard>

                <BiCard title="Activation History" padded={false}>
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {activationLogs.length === 0 && (
                            <p className="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No activity recorded yet.
                            </p>
                        )}
                        {activationLogs.map((log) => (
                            <div
                                key={log.id}
                                className="flex items-center justify-between px-6 py-3 text-sm"
                            >
                                <div>
                                    <p className="font-medium capitalize text-gray-900 dark:text-gray-100">
                                        {log.action}
                                        {log.device?.machine_name &&
                                            ` — ${log.device.machine_name}`}
                                    </p>
                                    {log.reason && (
                                        <p className="text-gray-500 dark:text-gray-400">
                                            {log.reason}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-3 text-gray-500 dark:text-gray-400">
                                    <BiBadge
                                        variant={
                                            log.result === 'success'
                                                ? 'success'
                                                : 'danger'
                                        }
                                    >
                                        {log.result}
                                    </BiBadge>
                                    {new Date(log.created_at).toLocaleString()}
                                </div>
                            </div>
                        ))}
                    </div>
                </BiCard>
            </div>

            <BiModal
                show={revoking}
                onClose={() => setRevoking(false)}
                title="Revoke license"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setRevoking(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="revoke-form"
                            variant="danger"
                            disabled={revokeForm.processing}
                        >
                            Revoke
                        </BiButton>
                    </>
                }
            >
                <form id="revoke-form" onSubmit={submitRevoke}>
                    <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        This immediately deactivates every device on this
                        license. This cannot be undone.
                    </p>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Reason
                    </label>
                    <TextInput
                        className="mt-1 block w-full"
                        value={revokeForm.data.reason}
                        onChange={(e) =>
                            revokeForm.setData('reason', e.target.value)
                        }
                    />
                    {revokeForm.errors.reason && (
                        <p className="mt-1 text-sm text-red-600">
                            {revokeForm.errors.reason}
                        </p>
                    )}
                </form>
            </BiModal>
        </PlatformLicensesLayout>
    );
}
