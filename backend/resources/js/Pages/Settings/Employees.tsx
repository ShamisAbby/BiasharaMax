import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import { useConfirm } from '@/Components/ConfirmDialog';
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

/**
 * Role selector for an employee.
 *
 * Deliberately a checkbox list rather than `<select multiple>`. The native
 * control needs a Ctrl/Cmd-click to add a second option — a plain click
 * silently REPLACES the whole selection — so it reads as a control that
 * only accepts one role, which is exactly how it was being reported. It
 * also can't be multi-selected at all by touch, and it renders as an
 * unstyled OS listbox that ignores the app's theme.
 *
 * Checkboxes make the "more than one" affordance obvious, work on touch,
 * and leave room to show what each role is for.
 */
function RolePicker({
    id,
    roles,
    selected,
    onChange,
    error,
}: {
    id: string;
    roles: Role[];
    selected: string[];
    onChange: (roleIds: string[]) => void;
    error?: string;
}) {
    const toggle = (roleId: string) =>
        onChange(
            selected.includes(roleId)
                ? selected.filter((value) => value !== roleId)
                : [...selected, roleId],
        );

    return (
        <div>
            <div className="flex items-baseline justify-between">
                <InputLabel htmlFor={id} value="Roles" />
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    {selected.length} selected
                </span>
            </div>

            <div
                id={id}
                role="group"
                aria-label="Roles"
                className="mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-300 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800"
            >
                {roles.map((role) => (
                    <label
                        key={role.id}
                        className="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-3 py-2.5 transition-colors last:border-b-0 hover:bg-gray-50 dark:border-gray-700/60 dark:hover:bg-gray-700/40"
                    >
                        <Checkbox
                            className="mt-0.5 shrink-0"
                            checked={selected.includes(role.id)}
                            onChange={() => toggle(role.id)}
                        />
                        <span className="min-w-0">
                            <span className="block text-sm font-medium text-gray-900 dark:text-gray-100">
                                {role.name}
                            </span>
                            {role.description && (
                                <span className="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    {role.description}
                                </span>
                            )}
                        </span>
                    </label>
                ))}
            </div>

            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                An employee can hold several roles at once and receives the
                combined permissions of all of them.
            </p>

            {/*
              Mirrors the server's `min:1`. A group of checkboxes can't use
              the native `required` attribute — that would demand EVERY box
              be ticked — so the rule is enforced by disabling submit, and
              the reason is stated rather than left to a dead button.
            */}
            {selected.length === 0 && (
                <p className="mt-1 text-xs font-medium text-amber-600 dark:text-amber-500">
                    Select at least one role.
                </p>
            )}

            <InputError message={error} className="mt-2" />
        </div>
    );
}

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
        role_ids: [
            roles.find((r) => r.slug !== 'business-owner')?.id ?? '',
        ].filter(Boolean),
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

            <RolePicker
                id="employee_role"
                roles={roles}
                selected={data.role_ids}
                onChange={(roleIds) => setData('role_ids', roleIds)}
                error={errors.role_ids}
            />

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
                <PrimaryButton
                    disabled={processing || data.role_ids.length === 0}
                >
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
        role_ids: employee.roles?.map((r) => r.id) ?? [],
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

            <RolePicker
                id="edit_employee_role"
                roles={roles}
                selected={data.role_ids}
                onChange={(roleIds) => setData('role_ids', roleIds)}
                error={errors.role_ids}
            />

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
                <PrimaryButton
                    disabled={processing || data.role_ids.length === 0}
                >
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
    const askConfirm = useConfirm();
    const [inviting, setInviting] = useState(false);
    const [editingEmployee, setEditingEmployee] = useState<Employee | null>(
        null,
    );

    const removeEmployee = (employee: Employee) => {
        askConfirm({
            title: `Remove ${employee.name} from this business?`,
            tone: 'danger',
            confirmLabel: 'Remove',
            onConfirm: () => {
                router.delete(route('settings.employees.destroy', employee.id));
            },
        });
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
                                        {/*
                                          Every role, not just the first.
                                          Showing `employee.role` alone made
                                          a second role invisible after it
                                          was assigned, so the assignment
                                          looked like it hadn't saved.
                                        */}
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            {employee.email} &middot;{' '}
                                            {employee.roles.length > 0
                                                ? employee.roles
                                                      .map((role) => role.name)
                                                      .join(', ')
                                                : 'No role'}
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
