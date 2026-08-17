import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import BiModal from '@/Components/Bi/BiModal';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import { useConfirm } from '@/Components/ConfirmDialog';
import Dropdown from '@/Components/Dropdown';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import {
    ArrowUturnLeftIcon,
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    EllipsisVerticalIcon,
    UserPlusIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import { Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface TicketRow {
    id: string;
    ticket_number: string;
    business: { id: string; name: string } | null;
    department: { id: string; name: string } | null;
    assigned_agent: { id: string; name: string | null } | null;
    category: string;
    priority: string;
    status: string;
    subject: string;
    created_at: string;
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    open: 'warning',
    pending: 'warning',
    in_progress: 'info',
    resolved: 'success',
    closed: 'neutral',
    reopened: 'danger',
};

const PRIORITY_VARIANT: Record<
    string,
    'success' | 'warning' | 'danger' | 'info' | 'neutral'
> = {
    low: 'neutral',
    medium: 'info',
    high: 'warning',
    urgent: 'danger',
};

/** Shared styling for the buttons inside the row menu. */
const MENU_ITEM =
    'flex w-full items-center gap-2 px-4 py-2 text-start text-sm leading-5 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:hover:bg-gray-800 dark:focus:bg-gray-800';

export default function SupportIndex({
    tickets,
    agents,
    filters,
}: {
    tickets: {
        data: TicketRow[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    departments: { id: string; name: string }[];
    agents: { id: string; name: string | null }[];
    filters: Record<string, string>;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [assigning, setAssigning] = useState<TicketRow | null>(null);
    const [agentId, setAgentId] = useState('');
    const confirm = useConfirm();

    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('platform.operations.support.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const onSearchSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const submitAssign: FormEventHandler = (e) => {
        e.preventDefault();
        if (!assigning) return;

        router.post(
            route('platform.operations.support.assign', assigning.id),
            { agent_id: agentId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAssigning(null);
                    setAgentId('');
                },
            },
        );
    };

    const resolve = (ticket: TicketRow) => {
        confirm({
            title: `Mark ${ticket.ticket_number} as resolved?`,
            // Says what the customer experiences, not what the database
            // does — an agent choosing between Resolve and Close needs to
            // know the difference is whether the customer can still reply.
            message:
                'The customer is told the problem is fixed. They can still reply, and replying reopens the ticket.',
            confirmLabel: 'Mark resolved',
            cancelLabel: 'Cancel',
            tone: 'info',
            onConfirm: () =>
                router.post(
                    route('platform.operations.support.resolve', ticket.id),
                    {},
                    { preserveScroll: true },
                ),
        });
    };

    const close = (ticket: TicketRow) => {
        confirm({
            title: `Close ${ticket.ticket_number}?`,
            message:
                'This ends the conversation — the customer cannot reply afterwards and would have to open a new ticket.',
            confirmLabel: 'Close ticket',
            cancelLabel: 'Cancel',
            tone: 'warning',
            onConfirm: () =>
                router.post(
                    route('platform.operations.support.close', ticket.id),
                    {},
                    { preserveScroll: true },
                ),
        });
    };

    const reopen = (ticket: TicketRow) => {
        router.post(
            route('platform.operations.support.reopen', ticket.id),
            {},
            { preserveScroll: true },
        );
    };

    const columns: BiTableColumn<TicketRow>[] = [
        {
            key: 'ticket_number',
            label: 'Ticket #',
            render: (t) => (
                <Link
                    href={route('platform.operations.support.show', t.id)}
                    className="font-mono text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                >
                    {t.ticket_number}
                </Link>
            ),
        },
        {
            key: 'subject',
            label: 'Subject',
            render: (t) => (
                <Link
                    href={route('platform.operations.support.show', t.id)}
                    className="font-medium text-gray-900 hover:underline dark:text-gray-100"
                >
                    {t.subject}
                </Link>
            ),
        },
        {
            key: 'business',
            label: 'Business',
            render: (t) => t.business?.name ?? '—',
        },
        {
            key: 'department',
            label: 'Department',
            render: (t) => t.department?.name ?? '—',
        },
        {
            key: 'agent',
            label: 'Agent',
            render: (t) =>
                t.assigned_agent?.name ?? (
                    <span className="text-gray-400">Unassigned</span>
                ),
        },
        {
            key: 'priority',
            label: 'Priority',
            render: (t) => (
                <BiBadge variant={PRIORITY_VARIANT[t.priority] ?? 'neutral'}>
                    {t.priority}
                </BiBadge>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (t) => (
                <BiBadge variant={STATUS_VARIANT[t.status] ?? 'neutral'}>
                    {t.status.replace('_', ' ')}
                </BiBadge>
            ),
        },
        {
            key: 'created_at',
            // Time as well as date: the first thing anyone asks about a
            // support queue is how long a ticket has been sitting there,
            // and "Aug 12" cannot answer that.
            label: 'Created at',
            render: (t) =>
                new Date(t.created_at).toLocaleString(undefined, {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }),
        },
        {
            key: 'actions',
            label: '',
            render: (t) => (
                <div className="flex items-center justify-end gap-2">
                    <Link
                        href={route('platform.operations.support.show', t.id)}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        <ChatBubbleLeftRightIcon className="h-4 w-4" />
                        Reply
                    </Link>

                    <Dropdown>
                        <Dropdown.Trigger>
                            <button
                                type="button"
                                aria-label={`Actions for ${t.ticket_number}`}
                                className="rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                            >
                                <EllipsisVerticalIcon className="h-5 w-5" />
                            </button>
                        </Dropdown.Trigger>

                        <Dropdown.Content contentClasses="py-1 bg-white dark:bg-gray-900">
                            <button
                                type="button"
                                onClick={() => {
                                    setAgentId(t.assigned_agent?.id ?? '');
                                    setAssigning(t);
                                }}
                                className={`${MENU_ITEM} text-gray-700 dark:text-gray-300`}
                            >
                                <UserPlusIcon className="h-4 w-4" />
                                Assign
                            </button>

                            {/*
                              Resolve and Close are hidden once already in
                              that state, and Reopen appears in their place.
                              A menu offering "Close" on a closed ticket is
                              the kind of thing that makes people doubt
                              whether the last click registered.
                            */}
                            {t.status !== 'resolved' &&
                                t.status !== 'closed' && (
                                    <button
                                        type="button"
                                        onClick={() => resolve(t)}
                                        className={`${MENU_ITEM} text-emerald-600 dark:text-emerald-400`}
                                    >
                                        <CheckCircleIcon className="h-4 w-4" />
                                        Resolve
                                    </button>
                                )}

                            {t.status !== 'closed' && (
                                <button
                                    type="button"
                                    onClick={() => close(t)}
                                    className={`${MENU_ITEM} text-gray-500 dark:text-gray-400`}
                                >
                                    <XCircleIcon className="h-4 w-4" />
                                    Close
                                </button>
                            )}

                            {(t.status === 'resolved' ||
                                t.status === 'closed') && (
                                <button
                                    type="button"
                                    onClick={() => reopen(t)}
                                    className={`${MENU_ITEM} text-amber-600 dark:text-amber-400`}
                                >
                                    <ArrowUturnLeftIcon className="h-4 w-4" />
                                    Reopen
                                </button>
                            )}
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            ),
        },
    ];

    return (
        <PlatformLayout>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Support Tickets
                    </h1>
                    <Link
                        href={route(
                            'platform.operations.support.knowledge-base.index',
                        )}
                        className="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        Knowledge Base →
                    </Link>
                </div>

                <BiDataGrid
                    columns={columns}
                    paginated={tickets}
                    rowKey={(t) => t.id}
                    emptyMessage="No tickets match these filters."
                    toolbar={
                        <>
                            <form
                                onSubmit={onSearchSubmit}
                                className="flex gap-2"
                            >
                                <TextInput
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search ticket or subject"
                                    className="w-64"
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
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                                <option value="reopened">Reopened</option>
                            </SelectInput>
                            <SelectInput
                                value={filters.priority ?? ''}
                                onChange={(e) =>
                                    applyFilters({ priority: e.target.value })
                                }
                            >
                                <option value="">All priorities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </SelectInput>
                        </>
                    }
                />
            </div>

            <BiModal
                show={assigning !== null}
                onClose={() => setAssigning(null)}
                title={
                    assigning
                        ? `Assign ${assigning.ticket_number}`
                        : 'Assign ticket'
                }
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setAssigning(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="assign-ticket-form"
                            disabled={agentId === ''}
                        >
                            Assign
                        </BiButton>
                    </>
                }
            >
                <form
                    id="assign-ticket-form"
                    onSubmit={submitAssign}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Agent
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={agentId}
                            onChange={(e) => setAgentId(e.target.value)}
                        >
                            <option value="">Choose an agent…</option>
                            {agents.map((agent) => (
                                <option key={agent.id} value={agent.id}>
                                    {agent.name ?? 'Unnamed agent'}
                                </option>
                            ))}
                        </SelectInput>
                        {agents.length === 0 && (
                            <p className="mt-2 text-sm text-amber-600 dark:text-amber-400">
                                No support agents exist yet. Add one under
                                Administration before assigning.
                            </p>
                        )}
                    </div>

                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Assigning moves the ticket to{' '}
                        <strong>in progress</strong>, so it stops appearing in
                        the platform alert feed as waiting for a first response.
                    </p>
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
