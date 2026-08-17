import BiCard from '@/Components/Bi/BiCard';
import BiChart from '@/Components/Bi/BiChart';
import BiEmptyState from '@/Components/Bi/BiEmptyState';
import PayrollLayout from '@/Layouts/PayrollLayout';
import { formatCurrency } from '@/lib/currency';
import {
    BanknotesIcon,
    BriefcaseIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ClockIcon,
    DocumentCheckIcon,
    ExclamationTriangleIcon,
    UserGroupIcon,
    UsersIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';

interface AttendanceStats {
    total: number;
    present: number;
    absent: number;
    late: number;
    on_leave: number;
    attendance_pct: number;
}

interface TrendRow {
    date: string;
    present: number;
    absent: number;
    late: number;
}

interface PayrollSummary {
    id: string;
    period_name: string;
    status: string;
    total_net: number;
}

interface UpcomingEvent {
    name: string;
    birth_date?: string;
    contract_end_date?: string;
    days_remaining?: number;
}

interface DeptStat {
    department: string;
    count: number;
}

interface Summary {
    attendance: AttendanceStats;
    overtime_hours_this_month: number;
    attendance_trend: TrendRow[];
    pending_leave_requests: number;
    current_payroll: PayrollSummary | null;
    last_paid_payroll: {
        period_name: string;
        total_net: number;
        paid_at: string;
    } | null;
    upcoming_birthdays: UpcomingEvent[];
    upcoming_contract_expiry: UpcomingEvent[];
    department_stats: DeptStat[];
}

const STATUS_COLORS: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    processing:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    approved:
        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
};

export default function HrDashboard({ summary }: { summary: Summary | null }) {
    if (!summary) {
        return (
            <PayrollLayout title="Dashboard">
                <BiEmptyState
                    title="No HR data yet"
                    description="Add employees and start tracking attendance to see your HR dashboard."
                    icon={<UsersIcon className="h-10 w-10" />}
                />
            </PayrollLayout>
        );
    }

    const {
        attendance,
        attendance_trend,
        department_stats,
        current_payroll,
        last_paid_payroll,
        upcoming_birthdays,
        upcoming_contract_expiry,
    } = summary;

    return (
        <PayrollLayout title="Dashboard">
            {/* KPI Grid */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <KpiCard
                    icon={<UsersIcon className="h-5 w-5" />}
                    iconBg="bg-indigo-600"
                    label="Total Employees"
                    value={attendance.total}
                />
                <KpiCard
                    icon={<CheckCircleIcon className="h-5 w-5" />}
                    iconBg="bg-emerald-600"
                    label="Present Today"
                    value={attendance.present}
                />
                <KpiCard
                    icon={<XCircleIcon className="h-5 w-5" />}
                    iconBg="bg-red-500"
                    label="Absent Today"
                    value={attendance.absent}
                />
                <KpiCard
                    icon={<ClockIcon className="h-5 w-5" />}
                    iconBg="bg-amber-500"
                    label="Late Today"
                    value={attendance.late}
                />
                <KpiCard
                    icon={<CalendarDaysIcon className="h-5 w-5" />}
                    iconBg="bg-purple-600"
                    label="On Leave"
                    value={attendance.on_leave}
                />
                <KpiCard
                    icon={<DocumentCheckIcon className="h-5 w-5" />}
                    iconBg="bg-pink-600"
                    label="Pending Leaves"
                    value={summary.pending_leave_requests}
                />
            </div>

            {/* Attendance % banner */}
            <div className="flex items-center gap-4 rounded-xl bg-indigo-600 px-6 py-4 text-white shadow">
                <UserGroupIcon className="h-8 w-8 shrink-0 text-indigo-200" />
                <div className="flex-1">
                    <p className="text-sm font-medium text-indigo-200">
                        Today's Attendance Rate
                    </p>
                    <div className="mt-1 flex items-center gap-3">
                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-indigo-700">
                            <div
                                className="h-2 rounded-full bg-white transition-all"
                                style={{
                                    width: `${attendance.attendance_pct}%`,
                                }}
                            />
                        </div>
                        <span className="text-2xl font-bold">
                            {attendance.attendance_pct}%
                        </span>
                    </div>
                </div>
                <div className="text-right">
                    <p className="text-sm text-indigo-200">
                        Overtime This Month
                    </p>
                    <p className="text-xl font-bold">
                        {summary.overtime_hours_this_month}h
                    </p>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Attendance trend chart */}
                <div className="lg:col-span-2">
                    <BiCard title="Attendance Trend" description="Last 7 days">
                        <BiChart
                            type="bar"
                            height={240}
                            labels={attendance_trend.map((r) =>
                                r.date.slice(5),
                            )}
                            datasets={[
                                {
                                    label: 'Present',
                                    data: attendance_trend.map(
                                        (r) => r.present,
                                    ),
                                    color: '#10B981',
                                },
                                {
                                    label: 'Absent',
                                    data: attendance_trend.map((r) => r.absent),
                                    color: '#EF4444',
                                },
                                {
                                    label: 'Late',
                                    data: attendance_trend.map((r) => r.late),
                                    color: '#F59E0B',
                                },
                            ]}
                            showLegend
                        />
                    </BiCard>
                </div>

                {/* Quick actions + payroll status */}
                <div className="space-y-4">
                    <BiCard title="Quick Actions">
                        <div className="space-y-2">
                            <Link
                                href={route('payroll.attendance.index')}
                                className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 dark:text-gray-300 dark:hover:bg-indigo-900/20"
                            >
                                <ClockIcon className="h-5 w-5 text-indigo-600" />
                                View Today's Attendance
                            </Link>
                            <Link
                                href={route('payroll.leave.index')}
                                className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 dark:text-gray-300 dark:hover:bg-indigo-900/20"
                            >
                                <CalendarDaysIcon className="h-5 w-5 text-purple-600" />
                                Manage Leave Requests
                                {summary.pending_leave_requests > 0 && (
                                    <span className="ml-auto rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-600">
                                        {summary.pending_leave_requests}
                                    </span>
                                )}
                            </Link>
                            <Link
                                href={route('payroll.employees.index')}
                                className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 dark:text-gray-300 dark:hover:bg-indigo-900/20"
                            >
                                <UsersIcon className="h-5 w-5 text-teal-600" />
                                Employee Profiles
                            </Link>
                            <Link
                                href={route('payroll.periods.index')}
                                className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 dark:text-gray-300 dark:hover:bg-indigo-900/20"
                            >
                                <BanknotesIcon className="h-5 w-5 text-emerald-600" />
                                Payroll Periods
                            </Link>
                        </div>
                    </BiCard>

                    {current_payroll && (
                        <BiCard title="Current Payroll">
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-500">
                                        {current_payroll.period_name}
                                    </span>
                                    <span
                                        className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_COLORS[current_payroll.status] ?? ''}`}
                                    >
                                        {current_payroll.status}
                                    </span>
                                </div>
                                <p className="text-xl font-bold text-gray-900 dark:text-white">
                                    {formatCurrency(current_payroll.total_net)}
                                </p>
                                <Link
                                    href={route('payroll.periods.index')}
                                    className="text-xs text-indigo-600 hover:underline"
                                >
                                    View period →
                                </Link>
                            </div>
                        </BiCard>
                    )}
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Department stats */}
                {department_stats.length > 0 && (
                    <BiCard title="Department Distribution">
                        <div className="space-y-2">
                            {department_stats.map((d) => (
                                <div
                                    key={d.department}
                                    className="flex items-center gap-3"
                                >
                                    <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                                        <BriefcaseIcon className="h-4 w-4 text-indigo-600" />
                                    </div>
                                    <span className="flex-1 text-sm text-gray-700 dark:text-gray-300">
                                        {d.department}
                                    </span>
                                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {d.count}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </BiCard>
                )}

                {/* Upcoming birthdays */}
                {upcoming_birthdays.length > 0 && (
                    <BiCard
                        title="Upcoming Birthdays"
                        description="Next 14 days"
                    >
                        <div className="space-y-2">
                            {upcoming_birthdays.map((b, i) => (
                                <div
                                    key={i}
                                    className="flex items-center gap-3"
                                >
                                    <span className="text-xl">🎂</span>
                                    <div>
                                        <p className="text-sm font-medium text-gray-800 dark:text-gray-100">
                                            {b.name}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {b.birth_date}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </BiCard>
                )}

                {/* Contract expiry alerts */}
                {upcoming_contract_expiry.length > 0 && (
                    <BiCard title="Contract Expiry Alert">
                        <div className="space-y-2">
                            {upcoming_contract_expiry.map((c, i) => (
                                <div
                                    key={i}
                                    className="flex items-center gap-3 rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-900/20"
                                >
                                    <ExclamationTriangleIcon className="h-5 w-5 shrink-0 text-amber-500" />
                                    <div>
                                        <p className="text-sm font-medium text-gray-800 dark:text-gray-100">
                                            {c.name}
                                        </p>
                                        <p className="text-xs text-amber-600 dark:text-amber-400">
                                            Expires in {c.days_remaining} days
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </BiCard>
                )}
            </div>
        </PayrollLayout>
    );
}

function KpiCard({
    icon,
    iconBg,
    label,
    value,
}: {
    icon: React.ReactNode;
    iconBg: string;
    label: string;
    value: number;
}) {
    return (
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
            <div
                className={`mb-2 flex h-9 w-9 items-center justify-center rounded-lg text-white ${iconBg}`}
            >
                {icon}
            </div>
            <p className="text-2xl font-bold text-gray-900 dark:text-white">
                {value}
            </p>
            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {label}
            </p>
        </div>
    );
}
