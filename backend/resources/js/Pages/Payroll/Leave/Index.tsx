import BiEmptyState from '@/Components/Bi/BiEmptyState';
import SelectInput from '@/Components/SelectInput';
import PayrollLayout from '@/Layouts/PayrollLayout';
import {
    CalendarDaysIcon,
    CheckIcon,
    PlusIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface LeaveType {
    id: string;
    name: string;
    code: string;
    color: string;
    days_per_year: number;
    is_paid: boolean;
}

interface LeaveBalance {
    leave_type: LeaveType;
    allocated_days: number;
    used_days: number;
    pending_days: number;
    available_days: number;
}

interface LeaveRequest {
    id: string;
    employee_profile: { user: { name: string } };
    leave_type: LeaveType;
    start_date: string;
    end_date: string;
    days_requested: number;
    is_half_day: boolean;
    status: string;
    reason: string;
    approved_by_user: { name: string } | null;
    approved_at: string | null;
    approval_notes: string | null;
}

const STATUS_META: Record<string, { label: string; color: string }> = {
    pending: { label: 'Pending', color: 'bg-amber-100 text-amber-700' },
    approved: { label: 'Approved', color: 'bg-emerald-100 text-emerald-700' },
    rejected: { label: 'Rejected', color: 'bg-red-100 text-red-700' },
    cancelled: { label: 'Cancelled', color: 'bg-gray-100 text-gray-600' },
};

export default function LeaveIndex({
    requests,
    balances,
    leaveTypes,
    canApprove,
    filters,
}: {
    requests: LeaveRequest[];
    balances: LeaveBalance[];
    leaveTypes: LeaveType[];
    canApprove: boolean;
    filters: { status?: string };
}) {
    const [showApplyModal, setShowApplyModal] = useState(false);
    const [rejectTarget, setRejectTarget] = useState<LeaveRequest | null>(null);

    const applyForm = useForm({
        leave_type_id: '',
        start_date: '',
        end_date: '',
        is_half_day: false,
        half_day_period: 'morning',
        reason: '',
    });

    const approveForm = useForm({});
    const rejectForm = useForm({ approval_notes: '' });

    function submitApply() {
        applyForm.post(route('payroll.leave.store'), {
            onSuccess: () => setShowApplyModal(false),
        });
    }

    function approve(req: LeaveRequest) {
        approveForm.post(route('payroll.leave.approve', req.id));
    }

    function submitReject() {
        if (!rejectTarget) return;
        rejectForm.post(route('payroll.leave.reject', rejectTarget.id), {
            onSuccess: () => setRejectTarget(null),
        });
    }

    function filterByStatus(s: string | undefined) {
        router.get(route('payroll.leave.index'), s ? { status: s } : {}, {
            preserveState: false,
        });
    }

    const STATUS_TABS = ['', 'pending', 'approved', 'rejected', 'cancelled'];

    return (
        <PayrollLayout title="Leave">
            {/* Balance cards */}
            {balances.length > 0 && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {balances.map((b) => (
                        <div
                            key={b.leave_type.id}
                            className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                        >
                            <div className="mb-2 flex items-center gap-2">
                                <span
                                    className="h-3 w-3 rounded-full"
                                    style={{
                                        backgroundColor: b.leave_type.color,
                                    }}
                                />
                                <p className="truncate text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    {b.leave_type.name}
                                </p>
                                {b.leave_type.is_paid && (
                                    <span className="ml-auto text-xs text-emerald-600">
                                        Paid
                                    </span>
                                )}
                            </div>
                            <div className="mb-1 flex justify-between text-xs text-gray-400">
                                <span>Used: {b.used_days}d</span>
                                <span>Pending: {b.pending_days}d</span>
                            </div>
                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div
                                    className="h-1.5 rounded-full transition-all"
                                    style={{
                                        width: `${Math.min(100, ((b.used_days + b.pending_days) / b.allocated_days) * 100)}%`,
                                        backgroundColor: b.leave_type.color,
                                    }}
                                />
                            </div>
                            <p className="mt-1 text-right text-sm font-bold text-gray-900 dark:text-white">
                                {b.available_days}d left
                            </p>
                        </div>
                    ))}
                </div>
            )}

            {/* Header + Apply button */}
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                    Leave Requests
                </h2>
                <button
                    onClick={() => setShowApplyModal(true)}
                    className="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    <PlusIcon className="h-4 w-4" />
                    Apply for Leave
                </button>
            </div>

            {/* Status tabs */}
            <div className="flex gap-2 overflow-x-auto">
                {STATUS_TABS.map((s) => (
                    <button
                        key={s || 'all'}
                        onClick={() => filterByStatus(s || undefined)}
                        className={`whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
                            (filters.status ?? '') === s
                                ? 'bg-indigo-600 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'
                        }`}
                    >
                        {s ? (STATUS_META[s]?.label ?? s) : 'All'}
                    </button>
                ))}
            </div>

            {/* Requests list */}
            {requests.length === 0 ? (
                <BiEmptyState
                    title="No leave requests"
                    description="Leave requests will appear here once submitted."
                    icon={<CalendarDaysIcon className="h-10 w-10" />}
                />
            ) : (
                <div className="space-y-3">
                    {requests.map((r) => (
                        <div
                            key={r.id}
                            className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                        >
                            <div className="flex flex-wrap items-start gap-4">
                                <div className="flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-semibold text-gray-900 dark:text-white">
                                            {r.employee_profile.user.name}
                                        </p>
                                        <span
                                            className="rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                                            style={{
                                                backgroundColor:
                                                    r.leave_type.color,
                                            }}
                                        >
                                            {r.leave_type.name}
                                        </span>
                                        <span
                                            className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_META[r.status]?.color ?? ''}`}
                                        >
                                            {STATUS_META[r.status]?.label ??
                                                r.status}
                                        </span>
                                        {r.is_half_day && (
                                            <span className="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700">
                                                Half Day
                                            </span>
                                        )}
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-300">
                                        <span>
                                            <CalendarDaysIcon className="mr-1 inline h-4 w-4" />
                                            {r.start_date} → {r.end_date}
                                        </span>
                                        <span className="font-medium">
                                            {r.days_requested}{' '}
                                            {r.days_requested === 1
                                                ? 'day'
                                                : 'days'}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm text-gray-500">
                                        {r.reason}
                                    </p>
                                    {r.approval_notes && (
                                        <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                                            {r.approval_notes}
                                        </p>
                                    )}
                                    {r.approved_by_user && (
                                        <p className="mt-1 text-xs text-gray-400">
                                            {r.status === 'approved'
                                                ? 'Approved'
                                                : 'Reviewed'}{' '}
                                            by {r.approved_by_user.name}
                                            {r.approved_at &&
                                                ` on ${r.approved_at.slice(0, 10)}`}
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-start gap-2">
                                    {canApprove && r.status === 'pending' && (
                                        <>
                                            <button
                                                onClick={() => approve(r)}
                                                disabled={
                                                    approveForm.processing
                                                }
                                                className="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                            >
                                                <CheckIcon className="h-4 w-4" />
                                                Approve
                                            </button>
                                            <button
                                                onClick={() =>
                                                    setRejectTarget(r)
                                                }
                                                className="flex items-center gap-1.5 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                                            >
                                                <XMarkIcon className="h-4 w-4" />
                                                Reject
                                            </button>
                                        </>
                                    )}
                                    {r.status === 'pending' && !canApprove && (
                                        <button
                                            onClick={() =>
                                                router.delete(
                                                    route(
                                                        'payroll.leave.destroy',
                                                        r.id,
                                                    ),
                                                )
                                            }
                                            className="text-xs text-red-500 hover:underline"
                                        >
                                            Cancel
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Apply Modal */}
            {showApplyModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Apply for Leave
                        </h3>
                        <div className="mt-4 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Leave Type
                                </label>
                                <SelectInput
                                    value={applyForm.data.leave_type_id}
                                    onChange={(e) =>
                                        applyForm.setData(
                                            'leave_type_id',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                >
                                    <option value="">Select type...</option>
                                    {leaveTypes.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.name} (
                                            {t.is_paid ? 'Paid' : 'Unpaid'})
                                        </option>
                                    ))}
                                </SelectInput>
                                {applyForm.errors.leave_type_id && (
                                    <p className="text-xs text-red-500">
                                        {applyForm.errors.leave_type_id}
                                    </p>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Start Date
                                    </label>
                                    <input
                                        type="date"
                                        value={applyForm.data.start_date}
                                        onChange={(e) =>
                                            applyForm.setData(
                                                'start_date',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    {applyForm.errors.start_date && (
                                        <p className="text-xs text-red-500">
                                            {applyForm.errors.start_date}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        End Date
                                    </label>
                                    <input
                                        type="date"
                                        value={applyForm.data.end_date}
                                        onChange={(e) =>
                                            applyForm.setData(
                                                'end_date',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    {applyForm.errors.end_date && (
                                        <p className="text-xs text-red-500">
                                            {applyForm.errors.end_date}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_half_day"
                                    checked={applyForm.data.is_half_day}
                                    onChange={(e) =>
                                        applyForm.setData(
                                            'is_half_day',
                                            e.target.checked,
                                        )
                                    }
                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600"
                                />
                                <label
                                    htmlFor="is_half_day"
                                    className="text-sm text-gray-700 dark:text-gray-300"
                                >
                                    Half day
                                </label>
                                {applyForm.data.is_half_day && (
                                    <SelectInput
                                        value={applyForm.data.half_day_period}
                                        onChange={(e) =>
                                            applyForm.setData(
                                                'half_day_period',
                                                e.target.value,
                                            )
                                        }
                                        className="ml-2 rounded-lg border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    >
                                        <option value="morning">Morning</option>
                                        <option value="afternoon">
                                            Afternoon
                                        </option>
                                    </SelectInput>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Reason
                                </label>
                                <textarea
                                    value={applyForm.data.reason}
                                    onChange={(e) =>
                                        applyForm.setData(
                                            'reason',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    placeholder="Brief reason for leave..."
                                />
                                {applyForm.errors.reason && (
                                    <p className="text-xs text-red-500">
                                        {applyForm.errors.reason}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="mt-5 flex justify-end gap-3">
                            <button
                                onClick={() => setShowApplyModal(false)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={submitApply}
                                disabled={applyForm.processing}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Submit Application
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Reject Modal */}
            {rejectTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Reject Leave Request
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            {rejectTarget.employee_profile.user.name} —{' '}
                            {rejectTarget.leave_type.name}
                        </p>
                        <div className="mt-4">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Reason for rejection
                            </label>
                            <textarea
                                value={rejectForm.data.approval_notes}
                                onChange={(e) =>
                                    rejectForm.setData(
                                        'approval_notes',
                                        e.target.value,
                                    )
                                }
                                rows={3}
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                placeholder="Explain the reason..."
                            />
                        </div>
                        <div className="mt-4 flex justify-end gap-3">
                            <button
                                onClick={() => setRejectTarget(null)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={submitReject}
                                disabled={rejectForm.processing}
                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </PayrollLayout>
    );
}
