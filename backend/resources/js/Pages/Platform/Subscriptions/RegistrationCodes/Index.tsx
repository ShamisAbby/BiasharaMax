import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import {
    ClipboardDocumentIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Plan {
    id: string;
    name: string;
}

interface CodeRow {
    id: string;
    code: string;
    status: 'available' | 'used' | 'expired';
    plan: { name: string } | null;
    billing_cycle: string;
    duration_months: number;
    description: string | null;
    expires_at: string | null;
    used_by: { name: string } | null;
    used_at: string | null;
    created_at: string;
}

interface PaginatedCodes {
    data: CodeRow[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const STATUS_VARIANT = {
    available: 'success',
    used: 'info',
    expired: 'warning',
} as const;

export default function RegistrationCodesIndex({
    codes,
    plans,
}: {
    codes: PaginatedCodes;
    plans: Plan[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [showCreate, setShowCreate] = useState(false);

    const copyCode = (code: string) => {
        navigator.clipboard
            .writeText(code)
            .then(() => notify(`Copied ${code}`, 'success'));
    };

    const deleteCode = (row: CodeRow) => {
        askConfirm({
            title: `Delete code ${row.code}?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route(
                        'platform.subscriptions.registration-codes.destroy',
                        row.id,
                    ),
                    {
                        onSuccess: () => notify('Code deleted.', 'success'),
                    },
                );
            },
        });
    };

    const columns: BiTableColumn<CodeRow>[] = [
        {
            key: 'code',
            label: 'Code',
            render: (row) => (
                <div className="flex items-center gap-2">
                    <span className="font-mono text-sm font-medium text-gray-900 dark:text-gray-100">
                        {row.code}
                    </span>
                    <button
                        onClick={() => copyCode(row.code)}
                        title="Copy"
                        className="text-gray-400 hover:text-indigo-500"
                    >
                        <ClipboardDocumentIcon className="h-3.5 w-3.5" />
                    </button>
                </div>
            ),
        },
        {
            key: 'plan',
            label: 'Plan',
            render: (row) => (
                <div>
                    <p className="text-sm text-gray-900 dark:text-gray-100">
                        {row.plan?.name ?? '—'}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        {row.duration_months}m · {row.billing_cycle}
                    </p>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <BiBadge variant={STATUS_VARIANT[row.status]}>
                    {row.status}
                </BiBadge>
            ),
        },
        {
            key: 'used_by',
            label: 'Used by',
            render: (row) =>
                row.used_by ? (
                    <div>
                        <p className="text-sm text-gray-700 dark:text-gray-300">
                            {row.used_by.name}
                        </p>
                        {row.used_at && (
                            <p className="text-xs text-gray-400">
                                {new Date(row.used_at).toLocaleDateString()}
                            </p>
                        )}
                    </div>
                ) : (
                    <span className="text-gray-400">—</span>
                ),
        },
        {
            key: 'expires_at',
            label: 'Expires',
            render: (row) =>
                row.expires_at ? (
                    new Date(row.expires_at).toLocaleDateString()
                ) : (
                    <span className="text-gray-400">Never</span>
                ),
        },
        {
            key: 'actions',
            label: '',
            align: 'right',
            render: (row) =>
                row.status === 'available' ? (
                    <button
                        onClick={() => deleteCode(row)}
                        className="text-red-400 hover:text-red-600"
                    >
                        <TrashIcon className="h-4 w-4" />
                    </button>
                ) : null,
        },
    ];

    return (
        <PlatformLayout>
            <Head title="Registration Codes" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Registration Codes
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Pre-generated codes that activate a subscription
                            upon registration.
                        </p>
                    </div>
                    <BiButton
                        onClick={() => setShowCreate(true)}
                        className="flex shrink-0 items-center gap-2"
                    >
                        <PlusIcon className="h-4 w-4" />
                        Generate Codes
                    </BiButton>
                </div>

                <BiDataGrid
                    columns={columns}
                    paginated={codes}
                    rowKey={(r) => r.id}
                    emptyMessage="No registration codes yet."
                />
            </div>

            {showCreate && (
                <CreateCodesModal
                    plans={plans}
                    onClose={() => setShowCreate(false)}
                    onDone={(msg) => {
                        notify(msg, 'success');
                        setShowCreate(false);
                    }}
                />
            )}
        </PlatformLayout>
    );
}

function CreateCodesModal({
    plans,
    onClose,
    onDone,
}: {
    plans: Plan[];
    onClose: () => void;
    onDone: (msg: string) => void;
}) {
    const form = useForm({
        plan_id: plans[0]?.id ?? '',
        billing_cycle: 'yearly',
        duration_months: '12',
        description: '',
        expires_at: '',
        quantity: '1',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('platform.subscriptions.registration-codes.store'), {
            onSuccess: () => onDone(`Codes generated.`),
        });
    };

    return (
        <BiModal
            show
            onClose={onClose}
            maxWidth="md"
            title="Generate Registration Codes"
            footer={
                <div className="flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={onClose}>
                        Cancel
                    </SecondaryButton>
                    <BiButton
                        type="submit"
                        form="create-codes-form"
                        disabled={form.processing}
                    >
                        Generate
                    </BiButton>
                </div>
            }
        >
            <form
                id="create-codes-form"
                onSubmit={submit}
                className="space-y-4"
            >
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Plan
                    </label>
                    <SelectInput
                        className="mt-1 block w-full"
                        value={form.data.plan_id}
                        onChange={(e) =>
                            form.setData('plan_id', e.target.value)
                        }
                    >
                        {plans.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </SelectInput>
                    {form.errors.plan_id && (
                        <p className="mt-1 text-xs text-red-600">
                            {form.errors.plan_id}
                        </p>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Billing cycle
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={form.data.billing_cycle}
                            onChange={(e) =>
                                form.setData('billing_cycle', e.target.value)
                            }
                        >
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </SelectInput>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Duration (months)
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            type="number"
                            min="1"
                            max="120"
                            value={form.data.duration_months}
                            onChange={(e) =>
                                form.setData('duration_months', e.target.value)
                            }
                        />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Quantity{' '}
                        <span className="text-gray-400">(max 100)</span>
                    </label>
                    <TextInput
                        className="mt-1 block w-full"
                        type="number"
                        min="1"
                        max="100"
                        value={form.data.quantity}
                        onChange={(e) =>
                            form.setData('quantity', e.target.value)
                        }
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Description{' '}
                        <span className="text-gray-400">(optional)</span>
                    </label>
                    <TextInput
                        className="mt-1 block w-full"
                        placeholder="e.g. Promo batch June 2026"
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Expiry date{' '}
                        <span className="text-gray-400">(optional)</span>
                    </label>
                    <TextInput
                        type="date"
                        className="mt-1 block w-full"
                        value={form.data.expires_at}
                        onChange={(e) =>
                            form.setData('expires_at', e.target.value)
                        }
                    />
                </div>
            </form>
        </BiModal>
    );
}
