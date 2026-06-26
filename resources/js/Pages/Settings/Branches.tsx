import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Branch } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function BranchForm({
    branch,
    onSaved,
}: {
    branch?: Branch;
    onSaved: () => void;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: branch?.name ?? '',
        code: branch?.code ?? '',
        phone: branch?.phone ?? '',
        address: branch?.address ?? '',
        city: branch?.city ?? '',
        status: branch?.status ?? 'active',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { onSuccess: onSaved };

        if (branch) {
            patch(route('settings.branches.update', branch.id), options);
        } else {
            post(route('settings.branches.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="branch_name" value="Branch name" />
                <TextInput
                    id="branch_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="branch_code" value="Code" />
                <TextInput
                    id="branch_code"
                    className="mt-1 block w-full uppercase"
                    value={data.code}
                    onChange={(e) =>
                        setData('code', e.target.value.toUpperCase())
                    }
                    required
                    disabled={branch?.is_main}
                />
                <InputError message={errors.code} className="mt-2" />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="branch_phone" value="Phone" />
                    <TextInput
                        id="branch_phone"
                        className="mt-1 block w-full"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="branch_city" value="City" />
                    <TextInput
                        id="branch_city"
                        className="mt-1 block w-full"
                        value={data.city}
                        onChange={(e) => setData('city', e.target.value)}
                    />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="branch_address" value="Address" />
                <TextInput
                    id="branch_address"
                    className="mt-1 block w-full"
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                />
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>
                    {branch ? 'Save changes' : 'Create branch'}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Branches({ branches }: { branches: Branch[] }) {
    const [editingBranch, setEditingBranch] = useState<Branch | null>(null);
    const [creating, setCreating] = useState(false);

    const deleteBranch = (branch: Branch) => {
        if (confirm(`Delete the "${branch.name}" branch?`)) {
            router.delete(route('settings.branches.destroy', branch.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Branches
                </h2>
            }
        >
            <Head title="Branches" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <Card
                        title="Branches"
                        description="Manage the physical locations your business operates from."
                        actions={
                            <PrimaryButton onClick={() => setCreating(true)}>
                                New branch
                            </PrimaryButton>
                        }
                    >
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {branches.map((branch) => (
                                <div
                                    key={branch.id}
                                    className="flex items-center justify-between py-3"
                                >
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                {branch.name}
                                            </p>
                                            <Badge variant="neutral">
                                                {branch.code}
                                            </Badge>
                                            {branch.is_main && (
                                                <Badge variant="info">
                                                    Main
                                                </Badge>
                                            )}
                                            {branch.status === 'inactive' && (
                                                <Badge variant="warning">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            {branch.warehouses_count ?? 0}{' '}
                                            warehouse
                                            {branch.warehouses_count === 1
                                                ? ''
                                                : 's'}{' '}
                                            &middot;{' '}
                                            {branch.employees_count ?? 0}{' '}
                                            employee
                                            {branch.employees_count === 1
                                                ? ''
                                                : 's'}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <SecondaryButton
                                            onClick={() =>
                                                setEditingBranch(branch)
                                            }
                                        >
                                            Edit
                                        </SecondaryButton>
                                        {!branch.is_main && (
                                            <DangerButton
                                                onClick={() =>
                                                    deleteBranch(branch)
                                                }
                                            >
                                                Delete
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
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        New branch
                    </h2>
                    <div className="mt-4">
                        <BranchForm onSaved={() => setCreating(false)} />
                    </div>
                </div>
            </Modal>

            <Modal
                show={editingBranch !== null}
                onClose={() => setEditingBranch(null)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Edit branch
                    </h2>
                    <div className="mt-4">
                        {editingBranch && (
                            <BranchForm
                                branch={editingBranch}
                                onSaved={() => setEditingBranch(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
