import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import PayrollLayout from '@/Layouts/PayrollLayout';
import { formatCurrency } from '@/lib/currency';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';

interface Allowance {
    id: string;
    allowance_type: string;
    amount: number;
    is_taxable: boolean;
    is_active: boolean;
}

interface PayslipSummary {
    id: string;
    period_name: string;
    gross_salary: number;
    net_salary: number;
    status: string;
    paid_at: string | null;
}

interface EmployeeDetail {
    id: string;
    employee_number: string;
    name: string;
    email: string;
    employment_date: string;
    employment_type: string;
    department: string | null;
    position: string | null;
    base_salary: number;
    salary_cycle: string;
    status: string;
    gross_salary: string;
    allowances: Allowance[];
    payslips: PayslipSummary[];
}

interface Props {
    employee: EmployeeDetail;
}

const STATUS_VARIANT: Record<
    string,
    'success' | 'warning' | 'neutral' | 'info'
> = {
    active: 'success',
    on_leave: 'warning',
    terminated: 'neutral',
    paid: 'success',
    approved: 'info',
    draft: 'neutral',
};

export default function EmployeeShow({ employee }: Props) {
    return (
        <PayrollLayout title={employee.name}>
            <div className="flex items-center gap-3">
                <Link
                    href={route('payroll.employees.index')}
                    className="text-gray-400 hover:text-gray-600"
                >
                    <ArrowLeftIcon className="h-5 w-5" />
                </Link>
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {employee.name}
                        </h3>
                        <Badge
                            variant={
                                STATUS_VARIANT[employee.status] ?? 'neutral'
                            }
                        >
                            {employee.status.replace('_', ' ')}
                        </Badge>
                        <span className="text-xs text-gray-400">
                            {employee.employee_number}
                        </span>
                    </div>
                    <p className="mt-0.5 text-sm text-gray-500">
                        {employee.email}
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-4 gap-4">
                <Card>
                    <p className="text-xs text-gray-500">Base Salary</p>
                    <p className="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                        {formatCurrency(employee.base_salary)}
                    </p>
                    <p className="mt-0.5 text-xs capitalize text-gray-400">
                        {employee.salary_cycle}
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-gray-500">Gross Salary</p>
                    <p className="mt-1 text-lg font-bold text-green-600">
                        {formatCurrency(parseFloat(employee.gross_salary))}
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-gray-500">Position</p>
                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                        {employee.position ?? '—'}
                    </p>
                    <p className="mt-0.5 text-xs text-gray-400">
                        {employee.department ?? ''}
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-gray-500">Employment</p>
                    <p className="mt-1 text-sm font-medium capitalize text-gray-900 dark:text-white">
                        {employee.employment_type.replace('_', ' ')}
                    </p>
                    <p className="mt-0.5 text-xs text-gray-400">
                        Since {employee.employment_date}
                    </p>
                </Card>
            </div>

            <div className="grid grid-cols-2 gap-6">
                <Card>
                    <h4 className="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                        Salary Allowances
                    </h4>
                    {employee.allowances.length === 0 ? (
                        <p className="text-sm text-gray-400">
                            No allowances configured.
                        </p>
                    ) : (
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-700">
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Type
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Amount
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Taxable
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Active
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {employee.allowances.map((a) => (
                                    <tr key={a.id}>
                                        <td className="py-1.5 pr-4 capitalize text-gray-700 dark:text-gray-300">
                                            {a.allowance_type}
                                        </td>
                                        <td className="py-1.5 pr-4 text-right font-mono">
                                            {formatCurrency(a.amount)}
                                        </td>
                                        <td className="py-1.5 pr-4 text-xs text-gray-500">
                                            {a.is_taxable ? 'Yes' : 'No'}
                                        </td>
                                        <td className="py-1.5">
                                            <Badge
                                                variant={
                                                    a.is_active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {a.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Card>

                <Card>
                    <h4 className="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                        Recent Payslips
                    </h4>
                    {employee.payslips.length === 0 ? (
                        <p className="text-sm text-gray-400">
                            No payslips yet.
                        </p>
                    ) : (
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-700">
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Period
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Gross
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Net
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {employee.payslips.map((p) => (
                                    <tr key={p.id}>
                                        <td className="py-1.5 pr-4 text-gray-700 dark:text-gray-300">
                                            {p.period_name}
                                        </td>
                                        <td className="py-1.5 pr-4 text-right font-mono">
                                            {formatCurrency(p.gross_salary)}
                                        </td>
                                        <td className="py-1.5 pr-4 text-right font-mono font-medium text-green-600">
                                            {formatCurrency(p.net_salary)}
                                        </td>
                                        <td className="py-1.5">
                                            <Badge
                                                variant={
                                                    (STATUS_VARIANT[
                                                        p.status
                                                    ] as
                                                        | 'success'
                                                        | 'warning'
                                                        | 'neutral'
                                                        | 'info'
                                                        | 'danger') ?? 'neutral'
                                                }
                                            >
                                                {p.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Card>
            </div>
        </PayrollLayout>
    );
}
