import BiEmptyState from '@/Components/Bi/BiEmptyState';
import { useConfirm } from '@/Components/ConfirmDialog';
import SelectInput from '@/Components/SelectInput';
import PayrollLayout from '@/Layouts/PayrollLayout';
import {
    CalendarDaysIcon,
    PencilIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

interface LeaveType {
    id: string;
    name: string;
    code: string;
    color: string;
    days_per_year: number;
    is_paid: boolean;
    requires_approval: boolean;
    requires_attachment: boolean;
    can_carry_forward: boolean;
    max_carry_forward_days: number;
    min_notice_days: number;
    gender_restricted: boolean;
    gender_restriction: string | null;
    is_active: boolean;
    is_system: boolean;
}

const GENDER_OPTIONS = [
    { value: '', label: 'No restriction' },
    { value: 'male', label: 'Male only' },
    { value: 'female', label: 'Female only' },
];

const COLORS = [
    '#4F46E5',
    '#059669',
    '#DC2626',
    '#D97706',
    '#7C3AED',
    '#0891B2',
    '#EA580C',
    '#BE185D',
];

export default function LeaveTypes({
    leaveTypes,
    canManage,
}: {
    leaveTypes: LeaveType[];
    canManage: boolean;
}) {
    const askConfirm = useConfirm();
    const [editTarget, setEditTarget] = useState<LeaveType | null>(null);
    const [showCreate, setShowCreate] = useState(false);
    const [showAllocate, setShowAllocate] = useState(false);

    const defaultData = {
        name: '',
        code: '',
        color: '#4F46E5',
        days_per_year: 21,
        is_paid: true,
        requires_approval: true,
        requires_attachment: false,
        can_carry_forward: false,
        max_carry_forward_days: 0,
        min_notice_days: 0,
        gender_restricted: false,
        gender_restriction: '',
    };

    const createForm = useForm(defaultData);
    const editForm = useForm({ ...defaultData });
    const deleteForm = useForm({});
    const allocateForm = useForm({ year: new Date().getFullYear() });

    function openEdit(lt: LeaveType) {
        editForm.setData({
            name: lt.name,
            code: lt.code,
            color: lt.color,
            days_per_year: lt.days_per_year,
            is_paid: lt.is_paid,
            requires_approval: lt.requires_approval,
            requires_attachment: lt.requires_attachment,
            can_carry_forward: lt.can_carry_forward,
            max_carry_forward_days: lt.max_carry_forward_days,
            min_notice_days: lt.min_notice_days,
            gender_restricted: lt.gender_restricted,
            gender_restriction: lt.gender_restriction ?? '',
        });
        setEditTarget(lt);
    }

    function submitCreate() {
        createForm.post(route('payroll.leave-types.store'), {
            onSuccess: () => {
                setShowCreate(false);
                createForm.reset();
            },
        });
    }

    function submitEdit() {
        if (!editTarget) return;
        editForm.patch(route('payroll.leave-types.update', editTarget.id), {
            onSuccess: () => setEditTarget(null),
        });
    }

    function deleteType(lt: LeaveType) {
        askConfirm({
            title: `Delete "${lt.name}"?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                deleteForm.delete(route('payroll.leave-types.destroy', lt.id));
            },
        });
    }

    function submitAllocate() {
        allocateForm.post(route('payroll.leave-types.allocate'), {
            onSuccess: () => setShowAllocate(false),
        });
    }

    return (
        <PayrollLayout title="Leave Types">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                    Leave Types
                </h2>
                {canManage && (
                    <div className="flex gap-2">
                        <button
                            onClick={() => setShowAllocate(true)}
                            className="flex items-center gap-2 rounded-lg border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                        >
                            <CalendarDaysIcon className="h-4 w-4" />
                            Allocate Year
                        </button>
                        <button
                            onClick={() => setShowCreate(true)}
                            className="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            <PlusIcon className="h-4 w-4" />
                            Add Leave Type
                        </button>
                    </div>
                )}
            </div>

            {leaveTypes.length === 0 ? (
                <BiEmptyState
                    title="No leave types configured"
                    description="Add leave types to enable leave management for your employees."
                    icon={<CalendarDaysIcon className="h-10 w-10" />}
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {leaveTypes.map((lt) => (
                        <div
                            key={lt.id}
                            className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                        >
                            <div
                                className="h-1.5"
                                style={{ backgroundColor: lt.color }}
                            />
                            <div className="p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <p className="font-semibold text-gray-900 dark:text-white">
                                                {lt.name}
                                            </p>
                                            {!lt.is_active && (
                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                                                    Inactive
                                                </span>
                                            )}
                                        </div>
                                        <p className="font-mono text-xs text-gray-400">
                                            {lt.code}
                                        </p>
                                    </div>
                                    {canManage && (
                                        <div className="flex items-center gap-1">
                                            <button
                                                onClick={() => openEdit(lt)}
                                                className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600"
                                            >
                                                <PencilIcon className="h-4 w-4" />
                                            </button>
                                            {!lt.is_system && (
                                                <button
                                                    onClick={() =>
                                                        deleteType(lt)
                                                    }
                                                    className="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                >
                                                    <TrashIcon className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div className="flex flex-col items-center rounded-lg bg-gray-50 p-2 dark:bg-gray-700">
                                        <p className="text-lg font-bold text-gray-900 dark:text-white">
                                            {lt.days_per_year}
                                        </p>
                                        <p className="text-gray-400">
                                            days/year
                                        </p>
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <Badge
                                            active={lt.is_paid}
                                            label="Paid"
                                        />
                                        <Badge
                                            active={lt.requires_approval}
                                            label="Needs approval"
                                        />
                                        <Badge
                                            active={lt.can_carry_forward}
                                            label="Carry forward"
                                        />
                                        <Badge
                                            active={lt.gender_restricted}
                                            label={
                                                lt.gender_restriction
                                                    ? `${lt.gender_restriction} only`
                                                    : 'Gender restricted'
                                            }
                                        />
                                    </div>
                                </div>
                                {lt.min_notice_days > 0 && (
                                    <p className="mt-2 text-xs text-gray-400">
                                        {lt.min_notice_days} days notice
                                        required
                                    </p>
                                )}
                                {lt.is_system && (
                                    <p className="mt-1 text-xs text-indigo-400">
                                        System type
                                    </p>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Create Modal */}
            {showCreate && (
                <LeaveTypeModal
                    title="Add Leave Type"
                    form={createForm}
                    onClose={() => setShowCreate(false)}
                    onSubmit={submitCreate}
                />
            )}

            {/* Edit Modal */}
            {editTarget && (
                <LeaveTypeModal
                    title={`Edit: ${editTarget.name}`}
                    form={editForm}
                    onClose={() => setEditTarget(null)}
                    onSubmit={submitEdit}
                />
            )}

            {/* Allocate Year Modal */}
            {showAllocate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Allocate Leave Balances
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            This creates leave balance records for all active
                            employees × all active leave types for the selected
                            year.
                        </p>
                        <div className="mt-4">
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Year
                            </label>
                            <input
                                type="number"
                                value={allocateForm.data.year}
                                onChange={(e) =>
                                    allocateForm.setData(
                                        'year',
                                        parseInt(e.target.value),
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            />
                        </div>
                        <div className="mt-4 flex justify-end gap-3">
                            <button
                                onClick={() => setShowAllocate(false)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={submitAllocate}
                                disabled={allocateForm.processing}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Allocate
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </PayrollLayout>
    );
}

function Badge({ active, label }: { active: boolean; label: string }) {
    if (!active) return null;
    return (
        <span className="rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
            {label}
        </span>
    );
}

function LeaveTypeModal({
    title,
    form,
    onClose,
    onSubmit,
}: {
    title: string;
    form: ReturnType<typeof useForm<any>>;
    onClose: () => void;
    onSubmit: () => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="max-h-screen w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                    {title}
                </h3>
                <div className="mt-4 space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Name
                            </label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            />
                            {form.errors.name && (
                                <p className="text-xs text-red-500">
                                    {form.errors.name}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Code
                            </label>
                            <input
                                type="text"
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData(
                                        'code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-700"
                                placeholder="e.g. ANNUAL"
                            />
                            {form.errors.code && (
                                <p className="text-xs text-red-500">
                                    {form.errors.code}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Days per year
                            </label>
                            <input
                                type="number"
                                min={0}
                                value={form.data.days_per_year}
                                onChange={(e) =>
                                    form.setData(
                                        'days_per_year',
                                        parseInt(e.target.value),
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Colour
                            </label>
                            <div className="mt-1 flex flex-wrap gap-1.5">
                                {COLORS.map((c) => (
                                    <button
                                        key={c}
                                        type="button"
                                        onClick={() => form.setData('color', c)}
                                        className={`h-6 w-6 rounded-full transition-transform ${form.data.color === c ? 'scale-125 ring-2 ring-indigo-400 ring-offset-1' : ''}`}
                                        style={{ backgroundColor: c }}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                        <Checkbox
                            label="Paid leave"
                            checked={form.data.is_paid}
                            onChange={(v) => form.setData('is_paid', v)}
                        />
                        <Checkbox
                            label="Requires approval"
                            checked={form.data.requires_approval}
                            onChange={(v) =>
                                form.setData('requires_approval', v)
                            }
                        />
                        <Checkbox
                            label="Requires attachment"
                            checked={form.data.requires_attachment}
                            onChange={(v) =>
                                form.setData('requires_attachment', v)
                            }
                        />
                        <Checkbox
                            label="Can carry forward"
                            checked={form.data.can_carry_forward}
                            onChange={(v) =>
                                form.setData('can_carry_forward', v)
                            }
                        />
                    </div>
                    {form.data.can_carry_forward && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Max carry-forward days
                            </label>
                            <input
                                type="number"
                                min={0}
                                value={form.data.max_carry_forward_days}
                                onChange={(e) =>
                                    form.setData(
                                        'max_carry_forward_days',
                                        parseInt(e.target.value),
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            />
                        </div>
                    )}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Minimum notice (days)
                        </label>
                        <input
                            type="number"
                            min={0}
                            value={form.data.min_notice_days}
                            onChange={(e) =>
                                form.setData(
                                    'min_notice_days',
                                    parseInt(e.target.value),
                                )
                            }
                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Gender restriction
                        </label>
                        <SelectInput
                            value={form.data.gender_restriction}
                            onChange={(e) => {
                                form.setData(
                                    'gender_restriction',
                                    e.target.value,
                                );
                                form.setData(
                                    'gender_restricted',
                                    !!e.target.value,
                                );
                            }}
                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                        >
                            {GENDER_OPTIONS.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </SelectInput>
                    </div>
                </div>
                <div className="mt-5 flex justify-end gap-3">
                    <button
                        onClick={onClose}
                        className="rounded-lg border border-gray-300 px-4 py-2 text-sm"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={onSubmit}
                        disabled={form.processing}
                        className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>
    );
}

function Checkbox({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
}) {
    return (
        <label className="flex cursor-pointer items-center gap-2">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-indigo-600"
            />
            <span className="text-gray-700 dark:text-gray-300">{label}</span>
        </label>
    );
}
