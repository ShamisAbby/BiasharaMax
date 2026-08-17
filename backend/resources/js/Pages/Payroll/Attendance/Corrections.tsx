import BiEmptyState from '@/Components/Bi/BiEmptyState';
import PayrollLayout from '@/Layouts/PayrollLayout';
import {
    AdjustmentsHorizontalIcon,
    CheckIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Correction {
    id: string;
    attendance_record: {
        id: string;
        attendance_date: string;
        clock_in_at: string | null;
        clock_out_at: string | null;
    };
    employee_profile: { user: { name: string } };
    requested_clock_in: string;
    requested_clock_out: string;
    reason: string;
    status: string;
    reviewer_notes: string | null;
    reviewed_at: string | null;
    reviewer: { name: string } | null;
    created_at: string;
}

const STATUS_META: Record<string, { label: string; color: string }> = {
    pending: { label: 'Pending', color: 'bg-amber-100 text-amber-700' },
    approved: { label: 'Approved', color: 'bg-emerald-100 text-emerald-700' },
    rejected: { label: 'Rejected', color: 'bg-red-100 text-red-700' },
};

export default function AttendanceCorrections({
    corrections,
    canApprove,
}: {
    corrections: Correction[];
    canApprove: boolean;
}) {
    const [rejectTarget, setRejectTarget] = useState<Correction | null>(null);
    const approveForm = useForm({});
    const rejectForm = useForm({ reviewer_notes: '' });

    function approve(correction: Correction) {
        approveForm.post(
            route('payroll.attendance.corrections.approve', correction.id),
        );
    }

    function submitReject() {
        if (!rejectTarget) return;
        rejectForm.post(
            route('payroll.attendance.corrections.reject', rejectTarget.id),
            {
                onSuccess: () => setRejectTarget(null),
            },
        );
    }

    const pending = corrections.filter((c) => c.status === 'pending');
    const resolved = corrections.filter((c) => c.status !== 'pending');

    return (
        <PayrollLayout title="Attendance Corrections">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                    Correction Requests
                    {pending.length > 0 && (
                        <span className="ml-2 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700">
                            {pending.length} pending
                        </span>
                    )}
                </h2>
            </div>

            {corrections.length === 0 ? (
                <BiEmptyState
                    title="No correction requests"
                    description="Employees can request attendance corrections from the Attendance page."
                    icon={<AdjustmentsHorizontalIcon className="h-10 w-10" />}
                />
            ) : (
                <div className="space-y-3">
                    {[...pending, ...resolved].map((c) => (
                        <div
                            key={c.id}
                            className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                        >
                            <div className="flex flex-wrap items-start gap-4">
                                <div className="flex-1 space-y-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-semibold text-gray-900 dark:text-white">
                                            {c.employee_profile.user.name}
                                        </p>
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_META[c.status]?.color ?? ''}`}
                                        >
                                            {STATUS_META[c.status]?.label ??
                                                c.status}
                                        </span>
                                    </div>
                                    <p className="text-sm text-gray-500">
                                        Record date:{' '}
                                        <strong>
                                            {
                                                c.attendance_record
                                                    .attendance_date
                                            }
                                        </strong>
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-6 text-sm">
                                        <div>
                                            <p className="text-xs font-medium uppercase text-gray-400">
                                                Current
                                            </p>
                                            <p className="text-gray-700 dark:text-gray-300">
                                                {c.attendance_record.clock_in_at?.slice(
                                                    11,
                                                    16,
                                                ) ?? '—'}{' '}
                                                →{' '}
                                                {c.attendance_record.clock_out_at?.slice(
                                                    11,
                                                    16,
                                                ) ?? '—'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs font-medium uppercase text-gray-400">
                                                Requested
                                            </p>
                                            <p className="font-semibold text-indigo-700 dark:text-indigo-300">
                                                {c.requested_clock_in.slice(
                                                    0,
                                                    5,
                                                )}{' '}
                                                →{' '}
                                                {c.requested_clock_out.slice(
                                                    0,
                                                    5,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        <span className="font-medium">
                                            Reason:{' '}
                                        </span>
                                        {c.reason}
                                    </div>
                                    {c.reviewer_notes && (
                                        <div className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                                            <span className="font-medium">
                                                Review note:{' '}
                                            </span>
                                            {c.reviewer_notes}
                                        </div>
                                    )}
                                    {c.reviewer && (
                                        <p className="text-xs text-gray-400">
                                            {c.status === 'approved'
                                                ? 'Approved'
                                                : 'Rejected'}{' '}
                                            by {c.reviewer.name} on{' '}
                                            {c.reviewed_at?.slice(0, 10)}
                                        </p>
                                    )}
                                </div>

                                {canApprove && c.status === 'pending' && (
                                    <div className="flex items-start gap-2">
                                        <button
                                            onClick={() => approve(c)}
                                            disabled={approveForm.processing}
                                            className="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                        >
                                            <CheckIcon className="h-4 w-4" />
                                            Approve
                                        </button>
                                        <button
                                            onClick={() => setRejectTarget(c)}
                                            className="flex items-center gap-1.5 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                                        >
                                            <XMarkIcon className="h-4 w-4" />
                                            Reject
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Reject dialog */}
            {rejectTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Reject Correction Request
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Employee: {rejectTarget.employee_profile.user.name}
                        </p>
                        <div className="mt-4">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Reason for rejection *
                            </label>
                            <textarea
                                value={rejectForm.data.reviewer_notes}
                                onChange={(e) =>
                                    rejectForm.setData(
                                        'reviewer_notes',
                                        e.target.value,
                                    )
                                }
                                rows={3}
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                placeholder="Explain why the correction is rejected..."
                            />
                            {rejectForm.errors.reviewer_notes && (
                                <p className="text-xs text-red-500">
                                    {rejectForm.errors.reviewer_notes}
                                </p>
                            )}
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
