import BiEmptyState from '@/Components/Bi/BiEmptyState';
import SelectInput from '@/Components/SelectInput';
import PayrollLayout from '@/Layouts/PayrollLayout';
import {
    AdjustmentsHorizontalIcon,
    CheckCircleIcon,
    ClockIcon,
    PauseIcon,
    PlayIcon,
    PlusIcon,
    UserGroupIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Employee {
    id: string;
    user: { name: string };
    department: string | null;
}

interface AttendanceRecord {
    id: string;
    employee_profile: Employee;
    attendance_date: string;
    status: string;
    clock_in_at: string | null;
    clock_out_at: string | null;
    break_start_at: string | null;
    break_end_at: string | null;
    regular_hours: number;
    overtime_hours: number;
    is_late: boolean;
    late_minutes: number;
    notes: string | null;
    correction: { status: string } | null;
}

interface DailyStats {
    total: number;
    present: number;
    absent: number;
    late: number;
    on_leave: number;
    attendance_pct: number;
}

const STATUS_META: Record<string, { label: string; color: string }> = {
    present: { label: 'Present', color: 'bg-emerald-100 text-emerald-700' },
    absent: { label: 'Absent', color: 'bg-red-100 text-red-700' },
    late: { label: 'Late', color: 'bg-amber-100 text-amber-700' },
    half_day: { label: 'Half Day', color: 'bg-orange-100 text-orange-700' },
    on_leave: { label: 'On Leave', color: 'bg-purple-100 text-purple-700' },
    holiday: { label: 'Holiday', color: 'bg-blue-100 text-blue-700' },
    weekend: { label: 'Weekend', color: 'bg-gray-100 text-gray-500' },
};

export default function AttendanceIndex({
    records,
    stats,
    date,
    employees,
    canManage,
    canApprove,
    canViewAll,
    openRecord,
    hasProfile,
}: {
    records: AttendanceRecord[];
    stats: DailyStats;
    date: string;
    employees: Employee[];
    canManage: boolean;
    canApprove: boolean;
    /** Whether this user may see the whole team, or only their own record. */
    canViewAll: boolean;
    openRecord: AttendanceRecord | null;
    hasProfile: boolean;
}) {
    const [showManualModal, setShowManualModal] = useState(false);
    const [showCorrectionModal, setShowCorrectionModal] =
        useState<AttendanceRecord | null>(null);

    const clockInForm = useForm<Record<string, string>>({});
    const clockOutForm = useForm({});
    const breakStartForm = useForm({});
    const breakEndForm = useForm({});

    const manualForm = useForm({
        employee_profile_id: '',
        attendance_date: date,
        status: 'present',
        clock_in_at: '09:00',
        clock_out_at: '17:00',
        notes: '',
    });

    const correctionForm = useForm({
        requested_clock_in: '',
        requested_clock_out: '',
        reason: '',
    });

    function handleDateChange(d: string) {
        router.get(
            route('payroll.attendance.index'),
            { date: d },
            { preserveState: false },
        );
    }

    function submitManual() {
        manualForm.post(route('payroll.attendance.manual'), {
            onSuccess: () => setShowManualModal(false),
        });
    }

    function openCorrectionModal(rec: AttendanceRecord) {
        correctionForm.setData({
            requested_clock_in: rec.clock_in_at?.slice(11, 16) ?? '',
            requested_clock_out: rec.clock_out_at?.slice(11, 16) ?? '',
            reason: '',
        });
        setShowCorrectionModal(rec);
    }

    function submitCorrection() {
        if (!showCorrectionModal) return;
        correctionForm.post(
            route(
                'payroll.attendance.corrections.store',
                showCorrectionModal.id,
            ),
            {
                onSuccess: () => setShowCorrectionModal(null),
            },
        );
    }

    return (
        <PayrollLayout title="Attendance">
            {/* Date picker + nav */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => handleDateChange(prevDay(date))}
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                    >
                        ←
                    </button>
                    <input
                        type="date"
                        value={date}
                        onChange={(e) => handleDateChange(e.target.value)}
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                    />
                    <button
                        onClick={() => handleDateChange(nextDay(date))}
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                    >
                        →
                    </button>
                    <button
                        onClick={() => handleDateChange(today())}
                        className="rounded-lg border border-indigo-300 px-3 py-2 text-sm text-indigo-600 hover:bg-indigo-50"
                    >
                        Today
                    </button>
                </div>
                <div className="flex items-center gap-2">
                    {canApprove && (
                        <a
                            href={route('payroll.attendance.corrections.index')}
                            className="flex items-center gap-2 rounded-lg border border-amber-300 px-4 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50"
                        >
                            <AdjustmentsHorizontalIcon className="h-4 w-4" />
                            Corrections
                        </a>
                    )}
                    {canManage && (
                        <button
                            onClick={() => setShowManualModal(true)}
                            className="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            <PlusIcon className="h-4 w-4" />
                            Manual Record
                        </button>
                    )}
                </div>
            </div>

            {/* My open record (clock-in/out controls) */}
            {openRecord && (
                <div className="rounded-xl bg-indigo-600 p-5 text-white shadow">
                    <p className="text-sm font-medium text-indigo-200">
                        Your attendance — {openRecord.attendance_date}
                    </p>
                    <div className="mt-3 flex flex-wrap items-center gap-4">
                        <div className="text-center">
                            <p className="text-xs text-indigo-200">
                                Clocked In
                            </p>
                            <p className="text-xl font-bold">
                                {openRecord.clock_in_at?.slice(11, 16) ?? '—'}
                            </p>
                        </div>
                        {openRecord.break_start_at &&
                            !openRecord.break_end_at && (
                                <div className="text-center">
                                    <p className="text-xs text-indigo-200">
                                        Break Start
                                    </p>
                                    <p className="text-xl font-bold">
                                        {openRecord.break_start_at.slice(
                                            11,
                                            16,
                                        )}
                                    </p>
                                </div>
                            )}
                        <div className="ml-auto flex gap-3">
                            {!openRecord.break_start_at && (
                                <button
                                    onClick={() =>
                                        breakStartForm.post(
                                            route(
                                                'payroll.attendance.break-start',
                                                openRecord.id,
                                            ),
                                        )
                                    }
                                    disabled={breakStartForm.processing}
                                    className="flex items-center gap-1.5 rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium hover:bg-indigo-400 disabled:opacity-50"
                                >
                                    <PauseIcon className="h-4 w-4" />
                                    Start Break
                                </button>
                            )}
                            {openRecord.break_start_at &&
                                !openRecord.break_end_at && (
                                    <button
                                        onClick={() =>
                                            breakEndForm.post(
                                                route(
                                                    'payroll.attendance.break-end',
                                                    openRecord.id,
                                                ),
                                            )
                                        }
                                        disabled={breakEndForm.processing}
                                        className="flex items-center gap-1.5 rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium hover:bg-indigo-400 disabled:opacity-50"
                                    >
                                        <PlayIcon className="h-4 w-4" />
                                        End Break
                                    </button>
                                )}
                            <button
                                onClick={() =>
                                    clockOutForm.post(
                                        route(
                                            'payroll.attendance.clock-out',
                                            openRecord.id,
                                        ),
                                    )
                                }
                                disabled={clockOutForm.processing}
                                className="flex items-center gap-1.5 rounded-lg bg-white px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                            >
                                <XCircleIcon className="h-4 w-4" />
                                Clock Out
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {hasProfile && !openRecord && date === today() && (
                <div className="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <div className="flex items-center justify-between p-5">
                        <div className="flex items-center gap-3">
                            <ClockIcon className="h-8 w-8 text-gray-400" />
                            <div>
                                <p className="font-medium text-gray-900 dark:text-white">
                                    You haven't clocked in today
                                </p>
                                <p className="text-sm text-gray-500">
                                    {new Date().toLocaleTimeString()}
                                </p>
                            </div>
                        </div>
                        <button
                            onClick={() =>
                                clockInForm.post(
                                    route('payroll.attendance.clock-in'),
                                )
                            }
                            disabled={clockInForm.processing}
                            className="flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            <CheckCircleIcon className="h-5 w-5" />
                            Clock In
                        </button>
                    </div>
                    {clockInForm.errors.clock_in && (
                        <p className="border-t border-red-100 px-5 py-2 text-sm text-red-600 dark:border-red-900/40 dark:text-red-400">
                            {clockInForm.errors.clock_in}
                        </p>
                    )}
                </div>
            )}

            {/* Daily stats — business-wide figures, so they're only shown
                to whoever may see the whole roster. Rendering them for
                everyone would just be a row of zeros. */}
            {canViewAll && (
                <div className="grid grid-cols-3 gap-3 sm:grid-cols-6">
                    <StatPill
                        label="Total"
                        value={stats.total}
                        icon={<UserGroupIcon className="h-4 w-4" />}
                        color="text-gray-700"
                    />
                    <StatPill
                        label="Present"
                        value={stats.present}
                        icon={<CheckCircleIcon className="h-4 w-4" />}
                        color="text-emerald-600"
                    />
                    <StatPill
                        label="Absent"
                        value={stats.absent}
                        icon={<XCircleIcon className="h-4 w-4" />}
                        color="text-red-500"
                    />
                    <StatPill
                        label="Late"
                        value={stats.late}
                        icon={<ClockIcon className="h-4 w-4" />}
                        color="text-amber-500"
                    />
                    <StatPill
                        label="On Leave"
                        value={stats.on_leave}
                        icon={<PauseIcon className="h-4 w-4" />}
                        color="text-purple-600"
                    />
                    <div className="flex flex-col items-center justify-center rounded-xl bg-indigo-50 p-3 dark:bg-indigo-900/20">
                        <p className="text-lg font-bold text-indigo-600">
                            {stats.attendance_pct}%
                        </p>
                        <p className="text-xs text-gray-500">Rate</p>
                    </div>
                </div>
            )}

            {/* Records table */}
            {records.length === 0 ? (
                <BiEmptyState
                    title="No attendance records"
                    description={
                        canViewAll
                            ? `No records found for ${date}.`
                            : `You have no attendance record for ${date}.`
                    }
                    icon={<ClockIcon className="h-10 w-10" />}
                />
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                {[
                                    'Employee',
                                    'Status',
                                    'Clock In',
                                    'Clock Out',
                                    'Regular',
                                    'Overtime',
                                    'Actions',
                                ].map((h) => (
                                    <th
                                        key={h}
                                        className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500"
                                    >
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                            {records.map((r) => (
                                <tr
                                    key={r.id}
                                    className="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                >
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900 dark:text-white">
                                            {r.employee_profile.user.name}
                                        </p>
                                        {r.employee_profile.department && (
                                            <p className="text-xs text-gray-400">
                                                {r.employee_profile.department}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_META[r.status]?.color ?? ''}`}
                                        >
                                            {STATUS_META[r.status]?.label ??
                                                r.status}
                                        </span>
                                        {r.is_late && (
                                            <span className="ml-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-600">
                                                +{r.late_minutes}m
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {r.clock_in_at?.slice(11, 16) ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {r.clock_out_at?.slice(11, 16) ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {Number(r.regular_hours).toFixed(1)}h
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        {Number(r.overtime_hours) > 0 ? (
                                            <span className="font-medium text-emerald-600">
                                                {Number(
                                                    r.overtime_hours,
                                                ).toFixed(1)}
                                                h
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {!r.correction && r.clock_in_at && (
                                            <button
                                                onClick={() =>
                                                    openCorrectionModal(r)
                                                }
                                                className="text-xs text-indigo-600 hover:underline"
                                            >
                                                Request correction
                                            </button>
                                        )}
                                        {r.correction && (
                                            <span
                                                className={`text-xs ${r.correction.status === 'pending' ? 'text-amber-600' : r.correction.status === 'approved' ? 'text-emerald-600' : 'text-red-500'}`}
                                            >
                                                Correction {r.correction.status}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Manual Record Modal */}
            {showManualModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Manual Attendance Record
                        </h3>
                        <div className="mt-4 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Employee
                                </label>
                                <SelectInput
                                    value={manualForm.data.employee_profile_id}
                                    onChange={(e) =>
                                        manualForm.setData(
                                            'employee_profile_id',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                >
                                    <option value="">Select employee...</option>
                                    {employees.map((e) => (
                                        <option key={e.id} value={e.id}>
                                            {e.user.name}
                                        </option>
                                    ))}
                                </SelectInput>
                                {manualForm.errors.employee_profile_id && (
                                    <p className="text-xs text-red-500">
                                        {manualForm.errors.employee_profile_id}
                                    </p>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Date
                                    </label>
                                    <input
                                        type="date"
                                        value={manualForm.data.attendance_date}
                                        onChange={(e) =>
                                            manualForm.setData(
                                                'attendance_date',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Status
                                    </label>
                                    <SelectInput
                                        value={manualForm.data.status}
                                        onChange={(e) =>
                                            manualForm.setData(
                                                'status',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    >
                                        <option value="present">Present</option>
                                        <option value="absent">Absent</option>
                                        <option value="half_day">
                                            Half Day
                                        </option>
                                        <option value="holiday">Holiday</option>
                                    </SelectInput>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Clock In
                                    </label>
                                    <input
                                        type="time"
                                        value={manualForm.data.clock_in_at}
                                        onChange={(e) =>
                                            manualForm.setData(
                                                'clock_in_at',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Clock Out
                                    </label>
                                    <input
                                        type="time"
                                        value={manualForm.data.clock_out_at}
                                        onChange={(e) =>
                                            manualForm.setData(
                                                'clock_out_at',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Notes
                                </label>
                                <input
                                    type="text"
                                    value={manualForm.data.notes}
                                    onChange={(e) =>
                                        manualForm.setData(
                                            'notes',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    placeholder="Optional notes..."
                                />
                            </div>
                        </div>
                        <div className="mt-5 flex justify-end gap-3">
                            <button
                                onClick={() => setShowManualModal(false)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={submitManual}
                                disabled={manualForm.processing}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Save Record
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Correction Request Modal */}
            {showCorrectionModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Request Attendance Correction
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            {showCorrectionModal.employee_profile.user.name} —{' '}
                            {showCorrectionModal.attendance_date}
                        </p>
                        <div className="mt-4 space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Corrected Clock In
                                    </label>
                                    <input
                                        type="time"
                                        value={
                                            correctionForm.data
                                                .requested_clock_in
                                        }
                                        onChange={(e) =>
                                            correctionForm.setData(
                                                'requested_clock_in',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Corrected Clock Out
                                    </label>
                                    <input
                                        type="time"
                                        value={
                                            correctionForm.data
                                                .requested_clock_out
                                        }
                                        onChange={(e) =>
                                            correctionForm.setData(
                                                'requested_clock_out',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Reason *
                                </label>
                                <textarea
                                    value={correctionForm.data.reason}
                                    onChange={(e) =>
                                        correctionForm.setData(
                                            'reason',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                    placeholder="Explain why this correction is needed..."
                                />
                                {correctionForm.errors.reason && (
                                    <p className="text-xs text-red-500">
                                        {correctionForm.errors.reason}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="mt-5 flex justify-end gap-3">
                            <button
                                onClick={() => setShowCorrectionModal(null)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={submitCorrection}
                                disabled={correctionForm.processing}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Submit Request
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </PayrollLayout>
    );
}

function StatPill({
    label,
    value,
    icon,
    color,
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
    color: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
            <div className={`mb-1 ${color}`}>{icon}</div>
            <p className={`text-lg font-bold ${color}`}>{value}</p>
            <p className="text-xs text-gray-400">{label}</p>
        </div>
    );
}

function today() {
    return new Date().toISOString().slice(0, 10);
}
function prevDay(d: string) {
    const dt = new Date(d);
    dt.setDate(dt.getDate() - 1);
    return dt.toISOString().slice(0, 10);
}
function nextDay(d: string) {
    const dt = new Date(d);
    dt.setDate(dt.getDate() + 1);
    return dt.toISOString().slice(0, 10);
}
