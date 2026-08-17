import Card from '@/Components/Card';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    ArrowDownTrayIcon,
    ArrowUpTrayIcon,
    ExclamationTriangleIcon,
    LockClosedIcon,
} from '@heroicons/react/24/outline';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

interface ImportPreview {
    business_id: string | null;
    belongs_to: string | null;
    business_name: string | null;
    generated_at: string | null;
    rows: Record<string, number>;
    total: number;
    skipped: Record<string, number>;
}

interface Pending {
    name: string;
    preview: ImportPreview | null;
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat().format(value);
}

export default function Backups({
    businessId,
    canExport,
    canRestore,
    businessName,
    tableCount,
    excluded,
    pending,
}: {
    businessId: string | null;
    canExport: boolean;
    canRestore: boolean;
    businessName: string | null;
    tableCount: number;
    /** table name → why it is left out of the backup. */
    excluded: Record<string, string>;
    pending: Pending | null;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [showExcluded, setShowExcluded] = useState(false);

    const uploadForm = useForm<{ backup: File | null }>({ backup: null });
    // `backup` isn't a field of this form, but the server reports
    // file-level failures (expired upload, unreadable file) under that key
    // during a restore — so it has to be part of the error shape.
    const restoreForm = useForm<{ confirmation: string; backup?: string }>({
        confirmation: '',
    });
    const cancelForm = useForm({});

    const upload: FormEventHandler = (event) => {
        event.preventDefault();
        uploadForm.post(route('settings.backups.preview'), {
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const restore: FormEventHandler = (event) => {
        event.preventDefault();
        restoreForm.post(route('settings.backups.restore'), {
            preserveScroll: true,
            onSuccess: () => restoreForm.reset(),
        });
    };

    const preview = pending?.preview ?? null;
    const tablesInBackup = preview ? Object.entries(preview.rows) : [];
    const skipped = preview ? Object.entries(preview.skipped) : [];

    // A backup only restores into the business it came from — primary keys
    // are preserved, so another business's file would collide with rows
    // that still exist. Checked here so the owner is told before typing a
    // confirmation, not after.
    const belongsToAnotherBusiness =
        preview?.belongs_to != null &&
        businessId != null &&
        preview.belongs_to !== businessId;

    // The confirmation must match the business name exactly — the same
    // check the server does, mirrored here so the button is only live once
    // it would actually succeed.
    const confirmationMatches =
        businessName !== null &&
        restoreForm.data.confirmation.trim() === businessName.trim();

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Backup &amp; Restore
                </h2>
            }
        >
            <Head title="Backup & Restore" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    <Card
                        title="Export a backup"
                        description={`Download every record ${businessName ?? 'this business'} owns as a .sql file you can keep, archive, or use to restore later.`}
                    >
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Covers {tableCount} tables — products, stock,
                                sales, purchases, customers, accounting and
                                payroll records.
                            </p>

                            {canExport ? (
                                <a
                                    href={route('settings.backups.export')}
                                    className="inline-flex shrink-0 items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
                                >
                                    <ArrowDownTrayIcon className="h-4 w-4" />
                                    Download .sql
                                </a>
                            ) : (
                                <p className="text-sm text-gray-400">
                                    You don&apos;t have permission to export.
                                </p>
                            )}
                        </div>

                        {/*
                          Stated on the screen rather than in documentation.
                          Someone relying on this for disaster recovery needs
                          to know what it does NOT contain before they need it.
                        */}
                        <div className="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                            <button
                                type="button"
                                onClick={() => setShowExcluded((open) => !open)}
                                className="flex w-full items-center gap-2 text-left text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                <LockClosedIcon className="h-4 w-4 shrink-0 text-gray-400" />
                                What is not included (
                                {Object.keys(excluded).length} items)
                                <span className="ml-auto text-xs text-gray-400">
                                    {showExcluded ? 'Hide' : 'Show'}
                                </span>
                            </button>

                            {showExcluded && (
                                <dl className="mt-4 space-y-2.5">
                                    {Object.entries(excluded).map(
                                        ([table, reason]) => (
                                            <div
                                                key={table}
                                                className="text-sm"
                                            >
                                                <dt className="font-mono text-xs text-gray-500 dark:text-gray-400">
                                                    {table}
                                                </dt>
                                                <dd className="text-gray-600 dark:text-gray-300">
                                                    {reason}
                                                </dd>
                                            </div>
                                        ),
                                    )}
                                </dl>
                            )}
                        </div>
                    </Card>

                    {canRestore && (
                        <Card
                            title="Restore from a backup"
                            description="Upload a .sql file exported from this screen. You'll see what's in it before anything changes."
                        >
                            {!pending && (
                                <form onSubmit={upload} className="space-y-4">
                                    <div>
                                        <InputLabel
                                            htmlFor="backup"
                                            value="Backup file (.sql)"
                                        />
                                        <input
                                            id="backup"
                                            ref={fileInput}
                                            type="file"
                                            accept=".sql,text/plain"
                                            onChange={(event) =>
                                                uploadForm.setData(
                                                    'backup',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            className="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-300 dark:file:bg-indigo-900/40 dark:file:text-indigo-300"
                                        />
                                        <InputError
                                            message={uploadForm.errors.backup}
                                            className="mt-2"
                                        />
                                    </div>

                                    <div className="flex justify-end">
                                        <PrimaryButton
                                            disabled={
                                                uploadForm.processing ||
                                                !uploadForm.data.backup
                                            }
                                        >
                                            <ArrowUpTrayIcon className="mr-2 h-4 w-4" />
                                            Inspect file
                                        </PrimaryButton>
                                    </div>
                                </form>
                            )}

                            {pending && preview && (
                                <div className="space-y-5">
                                    <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {pending.name}
                                        </p>
                                        <dl className="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                            <div className="flex gap-2">
                                                <dt className="text-gray-500">
                                                    From
                                                </dt>
                                                <dd className="text-gray-900 dark:text-gray-100">
                                                    {preview.business_name ??
                                                        'Unknown'}
                                                </dd>
                                            </div>
                                            <div className="flex gap-2">
                                                <dt className="text-gray-500">
                                                    Taken
                                                </dt>
                                                <dd className="text-gray-900 dark:text-gray-100">
                                                    {preview.generated_at
                                                        ? new Date(
                                                              preview.generated_at,
                                                          ).toLocaleString()
                                                        : 'Unknown'}
                                                </dd>
                                            </div>
                                            <div className="flex gap-2">
                                                <dt className="text-gray-500">
                                                    Records
                                                </dt>
                                                <dd className="text-gray-900 dark:text-gray-100">
                                                    {formatNumber(
                                                        preview.total,
                                                    )}{' '}
                                                    across{' '}
                                                    {tablesInBackup.length}{' '}
                                                    tables
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    {belongsToAnotherBusiness && (
                                        <p className="rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-300">
                                            This backup belongs to{' '}
                                            <strong>
                                                {preview.business_name ??
                                                    'another business'}
                                            </strong>{' '}
                                            and can&apos;t be restored here. A
                                            backup only restores into the
                                            business it was taken from.
                                        </p>
                                    )}

                                    {preview.total === 0 && (
                                        <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                            This file contains no records.
                                            Restoring it would leave the
                                            business empty.
                                        </p>
                                    )}

                                    {skipped.length > 0 && (
                                        <div className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                            <p className="font-medium">
                                                Some tables in this file will be
                                                ignored
                                            </p>
                                            <p className="mt-1">
                                                {skipped
                                                    .map(
                                                        ([table, count]) =>
                                                            `${table} (${count})`,
                                                    )
                                                    .join(', ')}
                                            </p>
                                        </div>
                                    )}

                                    {tablesInBackup.length > 0 && (
                                        <div className="max-h-56 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                            <table className="w-full text-sm">
                                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                                    {tablesInBackup.map(
                                                        ([table, count]) => (
                                                            <tr key={table}>
                                                                <td className="px-3 py-1.5 font-mono text-xs text-gray-600 dark:text-gray-300">
                                                                    {table}
                                                                </td>
                                                                <td className="px-3 py-1.5 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                                                    {formatNumber(
                                                                        count,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}

                                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20">
                                        <p className="flex items-start gap-2 text-sm font-semibold text-red-800 dark:text-red-300">
                                            <ExclamationTriangleIcon className="mt-0.5 h-5 w-5 shrink-0" />
                                            This replaces your current records
                                        </p>
                                        <p className="mt-2 text-sm leading-relaxed text-red-700 dark:text-red-200">
                                            Every product, sale, purchase,
                                            customer and accounting entry
                                            currently in{' '}
                                            {businessName ?? 'this business'} is
                                            deleted and replaced with what is in
                                            this file. Staff accounts, roles and
                                            your subscription are not affected.
                                            This cannot be undone — export a
                                            backup first if you want a way back.
                                        </p>

                                        <form
                                            onSubmit={restore}
                                            className="mt-4 space-y-3"
                                        >
                                            <div>
                                                <InputLabel
                                                    htmlFor="confirmation"
                                                    value={`Type "${businessName ?? ''}" to confirm`}
                                                />
                                                <TextInput
                                                    id="confirmation"
                                                    className="mt-1 block w-full"
                                                    value={
                                                        restoreForm.data
                                                            .confirmation
                                                    }
                                                    onChange={(event) =>
                                                        restoreForm.setData(
                                                            'confirmation',
                                                            event.target.value,
                                                        )
                                                    }
                                                    autoComplete="off"
                                                />
                                                <InputError
                                                    message={
                                                        restoreForm.errors
                                                            .confirmation
                                                    }
                                                    className="mt-2"
                                                />
                                                <InputError
                                                    message={
                                                        restoreForm.errors
                                                            .backup
                                                    }
                                                    className="mt-2"
                                                />
                                            </div>

                                            <div className="flex justify-end gap-2">
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        cancelForm.delete(
                                                            route(
                                                                'settings.backups.cancel',
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Discard file
                                                </SecondaryButton>
                                                <DangerButton
                                                    disabled={
                                                        restoreForm.processing ||
                                                        !confirmationMatches ||
                                                        belongsToAnotherBusiness
                                                    }
                                                >
                                                    Replace all data
                                                </DangerButton>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            )}
                        </Card>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
