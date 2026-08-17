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
import { PageProps } from '@/types';
import {
    EnvelopeIcon,
    PaperAirplaneIcon,
    ShieldCheckIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

interface PlatformUserRow {
    id: string;
    name: string;
    email: string;
    status: 'invited' | 'active' | 'suspended';
    user_type: 'admin' | 'business';
    is_owner: boolean;
    business: { id: string; name: string } | null;
    role: { id: string | null; name: string } | null;
    last_login_at: string | null;
    created_at: string;
}

interface PaginatedUsers {
    data: PlatformUserRow[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const STATUS_VARIANT = {
    invited: 'info',
    active: 'success',
    suspended: 'danger',
} as const;

type ComposeTarget = null | PlatformUserRow;

type TypeFilter = 'all' | 'admin' | 'business';

export default function PlatformUsersIndex({
    users,
    filters,
    activeUserCount,
    adminCount,
    businessUserCount,
}: {
    users: PaginatedUsers;
    filters: Record<string, string>;
    activeUserCount: number;
    adminCount: number;
    businessUserCount: number;
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const page = usePage<PageProps<{ flash: { broadcast_count?: number } }>>();

    const [search, setSearch] = useState(filters.search ?? '');
    const [composeTarget, setComposeTarget] = useState<
        ComposeTarget | 'closed'
    >('closed');

    const currentType: TypeFilter = (filters.type as TypeFilter) || 'all';

    useEffect(() => {
        const count = page.props.flash?.broadcast_count;
        if (count !== undefined) {
            notify(
                `Email sent to ${count} active user${count !== 1 ? 's' : ''}.`,
                'success',
            );
        }
    }, [page.props.flash]);

    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('platform.users.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const onSearchSubmit = (e: FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const setType = (type: TypeFilter) => applyFilters({ type, page: '1' });

    const activate = (user: PlatformUserRow) => {
        router.post(
            route('platform.users.activate', user.id),
            {},
            { onSuccess: () => notify(`${user.name} activated.`, 'success') },
        );
    };

    const deactivate = (user: PlatformUserRow) => {
        askConfirm({
            title: `Deactivate ${user.name}? They will be signed out.`,
            tone: 'danger',
            confirmLabel: 'Deactivate',
            onConfirm: () => {
                router.post(
                    route('platform.users.deactivate', user.id),
                    {},
                    {
                        onSuccess: () =>
                            notify(`${user.name} deactivated.`, 'warning'),
                    },
                );
            },
        });
    };

    const sendPasswordReset = (user: PlatformUserRow) => {
        router.post(
            route('platform.users.send-password-reset', user.id),
            {},
            {
                onSuccess: () =>
                    notify(
                        `Password reset email sent to ${user.email}.`,
                        'success',
                    ),
            },
        );
    };

    const deleteUser = (user: PlatformUserRow) => {
        askConfirm({
            title: `Permanently delete ${user.name}?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('platform.users.destroy', user.id), {
                    onSuccess: () =>
                        notify(`${user.name} has been deleted.`, 'success'),
                });
            },
        });
    };

    const columns: BiTableColumn<PlatformUserRow>[] = [
        {
            key: 'user',
            label: 'User',
            render: (user) => (
                <>
                    <p className="font-medium text-gray-900 dark:text-gray-100">
                        {user.name}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {user.email}
                    </p>
                </>
            ),
        },
        {
            key: 'type',
            label: 'Type',
            render: (user) =>
                user.user_type === 'admin' ? (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        <ShieldCheckIcon className="h-3.5 w-3.5" />
                        Platform Admin
                    </span>
                ) : (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
                        <UsersIcon className="h-3.5 w-3.5" />
                        Business User
                    </span>
                ),
        },
        {
            key: 'business',
            label: 'Business / Role',
            render: (user) =>
                user.user_type === 'admin' ? (
                    <span className="text-sm text-gray-500 dark:text-gray-400">
                        {user.role?.name ?? 'Super Admin'}
                    </span>
                ) : (
                    <>
                        <p className="text-gray-900 dark:text-gray-100">
                            {user.business?.name ?? '—'}
                        </p>
                        {user.is_owner && (
                            <BiBadge variant="info">Owner</BiBadge>
                        )}
                        {!user.is_owner && user.role && (
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                {user.role.name}
                            </p>
                        )}
                    </>
                ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (user) => (
                <BiBadge variant={STATUS_VARIANT[user.status]}>
                    {user.status}
                </BiBadge>
            ),
        },
        {
            key: 'actions',
            label: 'Actions',
            align: 'right',
            render: (user) => {
                if (user.user_type === 'admin') {
                    return (
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => setComposeTarget(user)}
                                className="flex items-center gap-1 text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                <EnvelopeIcon className="h-4 w-4" />
                                Email
                            </button>
                        </div>
                    );
                }

                return (
                    <div className="flex justify-end gap-3">
                        <button
                            onClick={() => setComposeTarget(user)}
                            className="flex items-center gap-1 text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            <EnvelopeIcon className="h-4 w-4" />
                            Email
                        </button>
                        <button
                            onClick={() => sendPasswordReset(user)}
                            className="text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            Reset password
                        </button>
                        {user.status === 'suspended' ? (
                            <>
                                <button
                                    onClick={() => activate(user)}
                                    className="text-emerald-600 hover:underline"
                                >
                                    Activate
                                </button>
                                <button
                                    onClick={() => deleteUser(user)}
                                    className="text-red-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </>
                        ) : (
                            <button
                                onClick={() => deactivate(user)}
                                className="text-red-600 hover:underline"
                            >
                                Deactivate
                            </button>
                        )}
                    </div>
                );
            },
        },
    ];

    const tabs: { key: TypeFilter; label: string; count: number }[] = [
        {
            key: 'all',
            label: 'All Users',
            count: adminCount + businessUserCount,
        },
        { key: 'business', label: 'Business Users', count: businessUserCount },
        { key: 'admin', label: 'Platform Admins', count: adminCount },
    ];

    return (
        <PlatformLayout>
            <Head title="Users" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            System Users
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            All users across the platform — {adminCount} admins
                            and {businessUserCount} business users.
                        </p>
                    </div>

                    <BiButton
                        onClick={() => setComposeTarget(null)}
                        className="flex shrink-0 items-center gap-2"
                    >
                        <UsersIcon className="h-4 w-4" />
                        Send Email to All
                    </BiButton>
                </div>

                {/* Type tabs */}
                <div className="flex gap-1 border-b border-gray-200 dark:border-gray-700">
                    {tabs.map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => setType(tab.key)}
                            className={`-mb-px flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                                currentType === tab.key
                                    ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                            }`}
                        >
                            {tab.label}
                            <span
                                className={`rounded-full px-2 py-0.5 text-xs ${
                                    currentType === tab.key
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                        : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                                }`}
                            >
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </div>

                <BiDataGrid
                    columns={columns}
                    paginated={users}
                    rowKey={(user) => `${user.user_type}-${user.id}`}
                    emptyMessage="No users match these filters."
                    toolbar={
                        <>
                            <form
                                onSubmit={onSearchSubmit}
                                className="flex gap-2"
                            >
                                <TextInput
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by name or email"
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
                                <option value="invited">Invited</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                            </SelectInput>
                        </>
                    }
                />
            </div>

            {composeTarget !== 'closed' && (
                <ComposeModal
                    target={composeTarget}
                    activeUserCount={activeUserCount}
                    onClose={() => setComposeTarget('closed')}
                    onSent={(msg) => {
                        notify(msg, 'success');
                        setComposeTarget('closed');
                    }}
                />
            )}
        </PlatformLayout>
    );
}

// ---------------------------------------------------------------------------
// Compose modal
// ---------------------------------------------------------------------------

function ComposeModal({
    target,
    activeUserCount,
    onClose,
    onSent,
}: {
    target: ComposeTarget;
    activeUserCount: number;
    onClose: () => void;
    onSent: (msg: string) => void;
}) {
    const isBroadcast = target === null;

    const form = useForm({ subject: '', body: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        const routeName = isBroadcast
            ? route('platform.users.broadcast')
            : route('platform.users.send-email', target!.id);

        form.post(routeName, {
            onSuccess: () => {
                form.reset();
                onSent(
                    isBroadcast
                        ? `Email sent to ${activeUserCount} active user${activeUserCount !== 1 ? 's' : ''}.`
                        : `Email sent to ${target!.name}.`,
                );
            },
        });
    };

    return (
        <BiModal
            show
            onClose={onClose}
            maxWidth="lg"
            title={
                isBroadcast
                    ? 'Send Email to All Active Users'
                    : `Send Email to ${target!.name}`
            }
            footer={
                <div className="flex items-center justify-end gap-3">
                    <SecondaryButton type="button" onClick={onClose}>
                        Cancel
                    </SecondaryButton>
                    <BiButton
                        type="submit"
                        form="compose-form"
                        disabled={form.processing}
                        className="flex items-center gap-2"
                    >
                        <PaperAirplaneIcon className="h-4 w-4" />
                        {form.processing ? 'Sending…' : 'Send'}
                    </BiButton>
                </div>
            }
        >
            <div
                className={`mb-5 flex items-center gap-3 rounded-lg px-4 py-3 text-sm ${
                    isBroadcast
                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                        : 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
                }`}
            >
                {isBroadcast ? (
                    <>
                        <UsersIcon className="h-5 w-5 shrink-0" />
                        <span>
                            This email will be sent to{' '}
                            <strong>
                                {activeUserCount} active business user
                                {activeUserCount !== 1 ? 's' : ''}
                            </strong>{' '}
                            on the platform.
                        </span>
                    </>
                ) : (
                    <>
                        <EnvelopeIcon className="h-5 w-5 shrink-0" />
                        <span>
                            To: <strong>{target!.name}</strong>{' '}
                            <span className="text-gray-500 dark:text-gray-400">
                                ({target!.email})
                            </span>
                        </span>
                    </>
                )}
            </div>

            <form id="compose-form" onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Subject <span className="text-red-500">*</span>
                    </label>
                    <TextInput
                        className="mt-1 block w-full"
                        placeholder="e.g. Important update from BiasharaMax"
                        value={form.data.subject}
                        onChange={(e) =>
                            form.setData('subject', e.target.value)
                        }
                        required
                    />
                    {form.errors.subject && (
                        <p className="mt-1 text-xs text-red-600">
                            {form.errors.subject}
                        </p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Message <span className="text-red-500">*</span>
                    </label>
                    <textarea
                        className="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        rows={7}
                        placeholder="Write your message here…"
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        required
                    />
                    {form.errors.body && (
                        <p className="mt-1 text-xs text-red-600">
                            {form.errors.body}
                        </p>
                    )}
                </div>
            </form>
        </BiModal>
    );
}
