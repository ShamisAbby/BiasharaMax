import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Branch, Employee, Role } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function InviteForm({
    roles,
    branches,
    onSaved,
}: {
    roles: Role[];
    branches: Branch[];
    onSaved: () => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        role_id: roles.find((r) => r.slug !== 'business-owner')?.id ?? '',
        branch_id: branches[0]?.id ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('settings.employees.store'), {
            onSuccess: () => {
                reset();
                onSaved();
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="employee_name" value="Full name" />
                <TextInput
                    id="employee_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="employee_email" value="Email" />
                <TextInput
                    id="employee_email"
                    type="email"
                    className="mt-1 block w-full"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    required
                />
                <InputError message={errors.email} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="employee_role" value="Role" />
                <SelectInput
                    id="employee_role"
                    className="mt-1 block w-full"
                    value={data.role_id}
                    onChange={(e) => setData('role_id', e.target.value)}
                    required
                >
                    {roles.map((role) => (
                        <option key={role.id} value={role.id}>
                            {role.name}
                        </option>
                    ))}
                </SelectInput>
                <InputError message={errors.role_id} className="mt-2" />
            </div>

            <div>
                <InputLabel
                    htmlFor="employee_branch"
                    value="Branch (optional)"
                />
                <SelectInput
                    id="employee_branch"
                    className="mt-1 block w-full"
                    value={data.branch_id}
                    onChange={(e) => setData('branch_id', e.target.value)}
                >
                    <option value="">No specific branch</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </SelectInput>
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>
                    Send invitation
                </PrimaryButton>
            </div>
        </form>
    );
}

function EditEmployeeForm({
    employee,
    roles,
    branches,
    onSaved,
}: {
    employee: Employee;
    roles: Role[];
    branches: Branch[];
    onSaved: () => void;
}) {
    const { data, setData, patch, processing, errors } = useForm({
        name: employee.name,
        role_id: employee.role?.id ?? '',
        branch_id: employee.branch?.id ?? '',
        status: employee.status === 'invited' ? 'active' : employee.status,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('settings.employees.update', employee.id), {
            onSuccess: onSaved,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="edit_employee_name" value="Full name" />
                <TextInput
                    id="edit_employee_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="edit_employee_role" value="Role" />
                <SelectInput
                    id="edit_employee_role"
                    className="mt-1 block w-full"
                    value={data.role_id}
                    onChange={(e) => setData('role_id', e.target.value)}
                    required
                >
                    {roles.map((role) => (
                        <option key={role.id} value={role.id}>
                            {role.name}
                        </option>
                    ))}
                </SelectInput>
                <InputError message={errors.role_id} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="edit_employee_branch" value="Branch" />
                <SelectInput
                    id="edit_employee_branch"
                    className="mt-1 block w-full"
                    value={data.branch_id}
                    onChange={(e) => setData('branch_id', e.target.value)}
                >
                    <option value="">No specific branch</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </SelectInput>
            </div>

            <div>
                <InputLabel htmlFor="edit_employee_status" value="Status" />
                <SelectInput
                    id="edit_employee_status"
                    className="mt-1 block w-full"
                    value={data.status}
                    onChange={(e) =>
                        setData(
                            'status',
                            e.target.value as 'active' | 'suspended',
                        )
                    }
                >
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </SelectInput>
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>
                    Save changes
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Employees({
    employees,
    roles,
    branches,
}: {
    employees: Employee[];
    roles: Role[];
    branches: Branch[];
}) {
    const [inviting, setInviting] = useState(false);
    const [editingEmployee, setEditingEmployee] = useState<Employee | null>(
        null,
    );

    const removeEmployee = (employee: Employee) => {
        if (confirm(`Remove ${employee.name} from this business?`)) {
            router.delete(route('settings.employees.destroy', employee.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Employees
                </h2>
            }
        >
            <Head title="Employees" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <Card
                        title="Team"
                        description="Invite staff and assign them a role and branch."
                        actions={
                            <PrimaryButton onClick={() => setInviting(true)}>
                                Invite employee
                            </PrimaryButton>
                        }
                    >
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {employees.map((employee) => (
                                <div
                                    key={employee.id}
                                    className="flex items-center justify-between py-3"
                                >
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                {employee.name}
                                            </p>
                                            {employee.is_owner && (
                                                <Badge variant="info">
                                                    Owner
                                                </Badge>
                                            )}
                                            {employee.status === 'invited' && (
                                                <Badge variant="warning">
                                                    Invited
                                                </Badge>
                                            )}
                                            {employee.status ===
                                                'suspended' && (
                                                <Badge variant="danger">
                                                    Suspended
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            {employee.email} &middot;{' '}
                                            {employee.role?.name ?? 'No role'}
                                            {employee.branch &&
                                                ` · ${employee.branch.name}`}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <SecondaryButton
                                            onClick={() =>
                                                setEditingEmployee(employee)
                                            }
                                        >
                                            Edit
                                        </SecondaryButton>
                                        {!employee.is_owner && (
                                            <DangerButton
                                                onClick={() =>
                                                    removeEmployee(employee)
                                                }
                                            >
                                                Remove
                                            </DangerButton>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>
            </div>

            <Modal
                show={inviting}
                onClose={() => setInviting(false)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Invite employee
                    </h2>
                    <div className="mt-4">
                        <InviteForm
                            roles={roles}
                            branches={branches}
                            onSaved={() => setInviting(false)}
                        />
                    </div>
                </div>
            </Modal>

            <Modal
                show={editingEmployee !== null}
                onClose={() => setEditingEmployee(null)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Edit employee
                    </h2>
                    <div className="mt-4">
                        {editingEmployee && (
                            <EditEmployeeForm
                                employee={editingEmployee}
                                roles={roles}
                                branches={branches}
                                onSaved={() => setEditingEmployee(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
