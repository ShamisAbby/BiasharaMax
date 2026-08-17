import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PayrollLayout from '@/Layouts/PayrollLayout';
import { formatCurrency } from '@/lib/currency';
import { PlusIcon, UserGroupIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface BusinessUser {
    id: string;
    name: string;
    email: string;
}

interface EmployeeRow {
    id: string;
    employee_number: string;
    name: string;
    email: string;
    position: string | null;
    department: string | null;
    base_salary: number;
    status: string;
    employment_type: string;
    gross_salary: string;
}

interface Props {
    employees: EmployeeRow[];
    businessUsers: BusinessUser[];
}

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'neutral'> = {
    active: 'success',
    on_leave: 'warning',
    terminated: 'neutral',
};

export default function EmployeesIndex({ employees, businessUsers }: Props) {
    const [creating, setCreating] = useState(false);

    const form = useForm({
        user_id: '',
        employee_number: '',
        employment_date: new Date().toISOString().slice(0, 10),
        employment_type: 'full_time',
        department: '',
        position: '',
        base_salary: '',
        salary_cycle: 'monthly',
        bank_account_number: '',
        bank_name: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('payroll.employees.store'), {
            onSuccess: () => {
                setCreating(false);
                form.reset();
            },
        });
    };

    return (
        <PayrollLayout title="Employees">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Employees
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Employee payroll profiles and salary configurations
                    </p>
                </div>
                <PrimaryButton onClick={() => setCreating(true)}>
                    <PlusIcon className="mr-1.5 h-4 w-4" />
                    Add Employee
                </PrimaryButton>
            </div>

            {employees.length === 0 ? (
                <Card>
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <UserGroupIcon className="mb-4 h-12 w-12 text-gray-300" />
                        <h4 className="text-base font-medium text-gray-900 dark:text-white">
                            No employee profiles
                        </h4>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Add employee profiles to start running payroll.
                        </p>
                    </div>
                </Card>
            ) : (
                <Card>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-700">
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        #
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Employee
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Position
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Base Salary
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Gross Salary
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Type
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {employees.map((emp) => (
                                    <tr key={emp.id}>
                                        <td className="py-2 pr-4 text-xs text-gray-400">
                                            {emp.employee_number}
                                        </td>
                                        <td className="py-2 pr-4">
                                            <Link
                                                href={route(
                                                    'payroll.employees.show',
                                                    emp.id,
                                                )}
                                                className="font-medium text-indigo-600 hover:text-indigo-800"
                                            >
                                                {emp.name}
                                            </Link>
                                            <p className="text-xs text-gray-400">
                                                {emp.email}
                                            </p>
                                        </td>
                                        <td className="py-2 pr-4 text-gray-600">
                                            {emp.position ?? '—'}
                                            {emp.department && (
                                                <span className="ml-1 text-xs text-gray-400">
                                                    ({emp.department})
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono">
                                            {formatCurrency(emp.base_salary)}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono font-medium">
                                            {formatCurrency(
                                                parseFloat(emp.gross_salary),
                                            )}
                                        </td>
                                        <td className="py-2 pr-4 text-xs capitalize text-gray-500">
                                            {emp.employment_type.replace(
                                                '_',
                                                ' ',
                                            )}
                                        </td>
                                        <td className="py-2">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANT[
                                                        emp.status
                                                    ] ?? 'neutral'
                                                }
                                            >
                                                {emp.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* Add Employee Modal */}
            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="lg"
            >
                <form onSubmit={submit} className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Add Employee Profile
                    </h3>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    htmlFor="e_user"
                                    value="User Account"
                                />
                                <SelectInput
                                    id="e_user"
                                    className="mt-1 block w-full"
                                    value={form.data.user_id}
                                    onChange={(e) =>
                                        form.setData('user_id', e.target.value)
                                    }
                                    required
                                >
                                    <option value="">Select user</option>
                                    {businessUsers.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name} — {u.email}
                                        </option>
                                    ))}
                                </SelectInput>
                                <InputError
                                    message={form.errors.user_id}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="e_num"
                                    value="Employee Number"
                                />
                                <TextInput
                                    id="e_num"
                                    className="mt-1 block w-full"
                                    value={form.data.employee_number}
                                    onChange={(e) =>
                                        form.setData(
                                            'employee_number',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="EMP-001"
                                    required
                                />
                                <InputError
                                    message={form.errors.employee_number}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    htmlFor="e_position"
                                    value="Position"
                                />
                                <TextInput
                                    id="e_position"
                                    className="mt-1 block w-full"
                                    value={form.data.position}
                                    onChange={(e) =>
                                        form.setData('position', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="e_dept"
                                    value="Department"
                                />
                                <TextInput
                                    id="e_dept"
                                    className="mt-1 block w-full"
                                    value={form.data.department}
                                    onChange={(e) =>
                                        form.setData(
                                            'department',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <InputLabel
                                    htmlFor="e_salary"
                                    value="Base Salary"
                                />
                                <TextInput
                                    id="e_salary"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    className="mt-1 block w-full"
                                    value={form.data.base_salary}
                                    onChange={(e) =>
                                        form.setData(
                                            'base_salary',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.base_salary}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="e_type"
                                    value="Employment Type"
                                />
                                <SelectInput
                                    id="e_type"
                                    className="mt-1 block w-full"
                                    value={form.data.employment_type}
                                    onChange={(e) =>
                                        form.setData(
                                            'employment_type',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="full_time">Full Time</option>
                                    <option value="part_time">Part Time</option>
                                    <option value="contract">Contract</option>
                                </SelectInput>
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="e_cycle"
                                    value="Salary Cycle"
                                />
                                <SelectInput
                                    id="e_cycle"
                                    className="mt-1 block w-full"
                                    value={form.data.salary_cycle}
                                    onChange={(e) =>
                                        form.setData(
                                            'salary_cycle',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="monthly">Monthly</option>
                                    <option value="bi_weekly">Bi-weekly</option>
                                    <option value="weekly">Weekly</option>
                                </SelectInput>
                            </div>
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="e_date"
                                value="Employment Date"
                            />
                            <TextInput
                                id="e_date"
                                type="date"
                                className="mt-1 block w-full"
                                value={form.data.employment_date}
                                onChange={(e) =>
                                    form.setData(
                                        'employment_date',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreating(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Add Employee'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </PayrollLayout>
    );
}
