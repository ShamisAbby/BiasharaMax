import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface BackupRecordRow {
    id: string;
    type: string;
    status: string;
    disk: string;
    triggered_by: string;
    file_path: string | null;
    file_size: number | null;
    started_at: string;
    completed_at: string | null;
    error_message: string | null;
}

interface SqlInspection {
    token: string;
    statements: number;
    inserts: number;
    tables: string[];
    is_recognised: boolean;
}

interface BackupFile {
    path: string;
    size: number;
    date: string;
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'neutral'
> = {
    running: 'warning',
    success: 'success',
    failed: 'danger',
};

export default function BackupIndex({
    records,
    files,
}: {
    records: {
        data: BackupRecordRow[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    files: BackupFile[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [running, setRunning] = useState(false);

    // Plain .sql import. Two steps on purpose: the admin sees what the
    // file would do before anything executes, and the token ties the
    // confirmation to the exact file that was inspected.
    const [sqlFile, setSqlFile] = useState<File | null>(null);
    const [sqlInspection, setSqlInspection] = useState<SqlInspection | null>(
        null,
    );
    const [sqlConfirmation, setSqlConfirmation] = useState('');
    const [sqlBusy, setSqlBusy] = useState(false);
    const [sqlError, setSqlError] = useState<string | null>(null);
    const [restoring, setRestoring] = useState<BackupRecordRow | null>(null);
    const [previewData, setPreviewData] = useState<{
        dump_file: string;
        size: number;
        tables_mentioned: number;
    } | null>(null);
    const [confirmation, setConfirmation] = useState('');

    const runBackup = (type: string) => {
        setRunning(true);
        router.post(
            route('platform.system.backups.run'),
            { type },
            {
                onSuccess: () => notify('Backup finished.', 'success'),
                onFinish: () => setRunning(false),
            },
        );
    };

    const destroy = (record: BackupRecordRow) => {
        askConfirm({
            title: 'Delete this backup permanently?',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('platform.system.backups.destroy', record.id),
                );
            },
        });
    };

    const openRestore = async (record: BackupRecordRow) => {
        setConfirmation('');
        setPreviewData(null);
        setRestoring(record);

        const response = await fetch(
            route('platform.system.backups.preview', record.id),
        );
        if (response.ok) {
            setPreviewData(await response.json());
        }
    };

    const submitRestore = (e: FormEvent) => {
        e.preventDefault();
        if (!restoring) return;

        router.post(
            route('platform.system.backups.restore', restoring.id),
            { confirmation },
            {
                onSuccess: () => {
                    setRestoring(null);
                    notify('Restore completed.', 'success');
                },
                onError: () =>
                    notify(
                        'Restore failed — check the filename and try again.',
                        'error',
                    ),
            },
        );
    };

    const columns: BiTableColumn<BackupRecordRow>[] = [
        { key: 'type', label: 'Type', render: (r) => r.type },
        {
            key: 'status',
            label: 'Status',
            render: (r) => (
                <BiBadge variant={STATUS_VARIANT[r.status] ?? 'neutral'}>
                    {r.status}
                </BiBadge>
            ),
        },
        {
            key: 'triggered_by',
            label: 'Triggered By',
            render: (r) => r.triggered_by,
        },
        {
            key: 'size',
            label: 'Size',
            render: (r) =>
                r.file_size ? `${(r.file_size / 1024).toFixed(1)} KB` : '—',
        },
        {
            key: 'started',
            label: 'Started',
            render: (r) => new Date(r.started_at).toLocaleString(),
        },
        {
            key: 'actions',
            label: 'Actions',
            align: 'right',
            render: (r) => (
                <div className="flex justify-end gap-3">
                    {r.status === 'success' && (
                        <>
                            <a
                                href={route(
                                    'platform.system.backups.download',
                                    r.id,
                                )}
                                className="text-indigo-600 hover:underline"
                            >
                                Download
                            </a>
                            <button
                                onClick={() => openRestore(r)}
                                className="text-amber-600 hover:underline"
                            >
                                Restore
                            </button>
                        </>
                    )}
                    <button
                        onClick={() => destroy(r)}
                        className="text-red-600 hover:underline"
                    >
                        Delete
                    </button>
                </div>
            ),
        },
    ];

    const submitSqlInspect = async (event: FormEvent) => {
        event.preventDefault();

        if (!sqlFile) return;

        setSqlBusy(true);
        setSqlError(null);

        try {
            const body = new FormData();
            body.append('backup', sqlFile);

            const response = await fetch(
                route('platform.system.backups.inspect-sql'),
                {
                    method: 'POST',
                    body,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') ?? '',
                    },
                },
            );

            const payload = await response.json();

            if (!response.ok) {
                setSqlError(payload.error ?? 'That file could not be read.');

                return;
            }

            if (!payload.is_recognised) {
                setSqlError(
                    'This is not a BiasharaMax database backup. Export one from this screen to see the expected format.',
                );

                return;
            }

            setSqlInspection(payload);
        } catch {
            setSqlError(
                'The upload failed. Check the file size and try again.',
            );
        } finally {
            setSqlBusy(false);
        }
    };

    const submitSqlRestore = (event: FormEvent) => {
        event.preventDefault();

        if (!sqlInspection) return;

        router.post(
            route('platform.system.backups.restore-sql'),
            { token: sqlInspection.token, confirmation: sqlConfirmation },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSqlInspection(null);
                    setSqlConfirmation('');
                    setSqlFile(null);
                    notify('Database restored.', 'success');
                },
            },
        );
    };

    return (
        <PlatformLayout>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Backup & Restore
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {files.length} backup files on disk.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <BiButton
                            variant="secondary"
                            disabled={running}
                            onClick={() => runBackup('database')}
                        >
                            Backup Database
                        </BiButton>
                        <BiButton
                            disabled={running}
                            onClick={() => runBackup('full')}
                        >
                            Full Backup
                        </BiButton>
                        {/* Plain .sql, written in PHP — works even where the
                            mysqldump binary isn't on the web process's PATH,
                            which is what makes the zip backups fail here. */}
                        <a
                            href={route('platform.system.backups.export-sql')}
                            className="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            Export .sql
                        </a>
                    </div>
                </div>

                <BiDataGrid
                    columns={columns}
                    paginated={records}
                    rowKey={(r) => r.id}
                    emptyMessage="No backups recorded yet."
                />

                <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Restore from a .sql file
                    </h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Upload a .sql file exported from this screen. You will
                        see what it contains before anything runs.
                    </p>

                    {!sqlInspection && (
                        <form
                            onSubmit={submitSqlInspect}
                            className="mt-4 flex flex-wrap items-center gap-3"
                        >
                            <input
                                type="file"
                                accept=".sql,text/plain"
                                onChange={(event) => {
                                    setSqlFile(event.target.files?.[0] ?? null);
                                    setSqlError(null);
                                }}
                                className="text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-300 dark:file:bg-indigo-900/40 dark:file:text-indigo-300"
                            />
                            <BiButton
                                type="submit"
                                variant="secondary"
                                disabled={!sqlFile || sqlBusy}
                            >
                                {sqlBusy ? 'Reading…' : 'Inspect file'}
                            </BiButton>
                        </form>
                    )}

                    {sqlError && (
                        <p className="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                            {sqlError}
                        </p>
                    )}

                    {sqlInspection && (
                        <form
                            onSubmit={submitSqlRestore}
                            className="mt-4 space-y-4"
                        >
                            <div className="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700">
                                <p className="text-gray-700 dark:text-gray-300">
                                    {sqlInspection.statements.toLocaleString()}{' '}
                                    statements,{' '}
                                    {sqlInspection.inserts.toLocaleString()}{' '}
                                    inserts, {sqlInspection.tables.length}{' '}
                                    tables.
                                </p>
                            </div>

                            <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20">
                                <p className="text-sm font-semibold text-red-800 dark:text-red-300">
                                    This replaces every business on the platform
                                </p>
                                <p className="mt-2 text-sm leading-relaxed text-red-700 dark:text-red-200">
                                    Tables are dropped and recreated. MySQL
                                    commits implicitly on schema changes, so a
                                    restore that fails partway cannot be rolled
                                    back — it will leave the database partly
                                    restored. Take a backup first.
                                </p>

                                <div className="mt-4 flex flex-wrap items-end gap-3">
                                    <div className="min-w-[16rem] flex-1">
                                        <label
                                            htmlFor="sql-confirm"
                                            className="block text-xs font-medium text-red-800 dark:text-red-300"
                                        >
                                            Type RESTORE DATABASE to confirm
                                        </label>
                                        <TextInput
                                            id="sql-confirm"
                                            className="mt-1 block w-full"
                                            value={sqlConfirmation}
                                            onChange={(event) =>
                                                setSqlConfirmation(
                                                    event.target.value,
                                                )
                                            }
                                            autoComplete="off"
                                        />
                                    </div>
                                    <div className="flex gap-2">
                                        <SecondaryButton
                                            type="button"
                                            onClick={() => {
                                                setSqlInspection(null);
                                                setSqlConfirmation('');
                                                setSqlFile(null);
                                            }}
                                        >
                                            Cancel
                                        </SecondaryButton>
                                        <BiButton
                                            type="submit"
                                            variant="danger"
                                            disabled={
                                                sqlConfirmation.trim() !==
                                                'RESTORE DATABASE'
                                            }
                                        >
                                            Restore database
                                        </BiButton>
                                    </div>
                                </div>
                            </div>
                        </form>
                    )}
                </div>
            </div>

            <BiModal
                show={restoring !== null}
                onClose={() => setRestoring(null)}
                title="Restore backup — destructive action"
                maxWidth="lg"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setRestoring(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="restore-form"
                            variant="danger"
                            disabled={!previewData}
                        >
                            Restore now
                        </BiButton>
                    </>
                }
            >
                <form
                    id="restore-form"
                    onSubmit={submitRestore}
                    className="space-y-4"
                >
                    <p className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                        This will overwrite the current database with the
                        contents of this backup. This cannot be undone.
                    </p>

                    {previewData ? (
                        <div className="text-sm text-gray-700 dark:text-gray-300">
                            <p>
                                Dump file:{' '}
                                <span className="font-mono">
                                    {previewData.dump_file}
                                </span>
                            </p>
                            <p>
                                Size: {(previewData.size / 1024).toFixed(1)} KB
                            </p>
                            <p>
                                Tables in dump: {previewData.tables_mentioned}
                            </p>
                        </div>
                    ) : (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Loading preview...
                        </p>
                    )}

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Type the backup filename (
                            {restoring?.file_path?.split('/').pop()}) to confirm
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={confirmation}
                            onChange={(e) => setConfirmation(e.target.value)}
                        />
                    </div>
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
