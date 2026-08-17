import Badge from '@/Components/Badge';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import SalesLayout from '@/Layouts/SalesLayout';
import { formatCurrency } from '@/lib/currency';
import { Customer } from '@/types/sales';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: {
        data: Customer[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    filters: { search?: string; customer_type?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Customer | null>(null);

    const createForm = useForm({
        name: '',
        phone: '',
        email: '',
        address: '',
        city: '',
        customer_type: 'cash',
        credit_limit: '0',
        notes: '',
    });

    const editForm = useForm({
        name: '',
        phone: '',
        email: '',
        address: '',
        city: '',
        customer_type: 'cash',
        credit_limit: '0',
        notes: '',
    });

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('sales.customers.index'),
            { ...filters, search },
            { preserveState: true },
        );
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('sales.customers.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    };

    const openEdit = (customer: Customer) => {
        editForm.setData({
            name: customer.name,
            phone: customer.phone ?? '',
            email: customer.email ?? '',
            address: customer.address ?? '',
            city: customer.city ?? '',
            customer_type: customer.customer_type,
            credit_limit: customer.credit_limit,
            notes: customer.notes ?? '',
        });
        setEditing(customer);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('sales.customers.update', editing.id), {
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <SalesLayout title="Customers">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <form onSubmit={submitSearch}>
                    <TextInput
                        placeholder="Search customers..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-64"
                    />
                </form>
                <PrimaryButton onClick={() => setCreating(true)}>
                    Add Customer
                </PrimaryButton>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'Name',
                                'Contact',
                                'Type',
                                'Credit Limit',
                                'Balance',
                                '',
                            ].map((h) => (
                                <th
                                    key={h}
                                    className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {customers.data.map((customer) => (
                            <tr
                                key={customer.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {customer.name}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {customer.phone ?? customer.email ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={
                                            customer.customer_type === 'credit'
                                                ? 'info'
                                                : 'neutral'
                                        }
                                    >
                                        {customer.customer_type}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {customer.customer_type === 'credit'
                                        ? formatCurrency(customer.credit_limit)
                                        : '—'}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <span
                                        className={
                                            Number(customer.current_balance) > 0
                                                ? 'font-medium text-amber-600'
                                                : 'text-gray-700 dark:text-gray-300'
                                        }
                                    >
                                        {formatCurrency(
                                            customer.current_balance,
                                        )}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <Link
                                        href={route(
                                            'crm.customers.show',
                                            customer.id,
                                        )}
                                        className="mr-3 text-indigo-600 hover:underline"
                                    >
                                        CRM Profile
                                    </Link>
                                    <button
                                        onClick={() => openEdit(customer)}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {customers.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No customers yet. Add one to start tracking credit
                        sales.
                    </p>
                )}
            </div>

            <Modal show={creating} onClose={() => setCreating(false)}>
                <CustomerForm
                    form={createForm}
                    onSubmit={submitCreate}
                    onCancel={() => setCreating(false)}
                    submitLabel="Add Customer"
                />
            </Modal>

            <Modal show={editing !== null} onClose={() => setEditing(null)}>
                <CustomerForm
                    form={editForm}
                    onSubmit={submitEdit}
                    onCancel={() => setEditing(null)}
                    submitLabel="Save Changes"
                />
            </Modal>
        </SalesLayout>
    );
}

function CustomerForm({
    form,
    onSubmit,
    onCancel,
    submitLabel,
}: {
    form: ReturnType<
        typeof useForm<{
            name: string;
            phone: string;
            email: string;
            address: string;
            city: string;
            customer_type: string;
            credit_limit: string;
            notes: string;
        }>
    >;
    onSubmit: (e: FormEvent) => void;
    onCancel: () => void;
    submitLabel: string;
}) {
    return (
        <form onSubmit={onSubmit} className="p-6">
            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                Customer details
            </h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <TextInput
                        placeholder="Name"
                        className="block w-full"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    <InputError message={form.errors.name} className="mt-2" />
                </div>
                <TextInput
                    placeholder="Phone"
                    className="block w-full"
                    value={form.data.phone}
                    onChange={(e) => form.setData('phone', e.target.value)}
                />
                <TextInput
                    placeholder="Email"
                    className="block w-full"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
                <TextInput
                    placeholder="City"
                    className="block w-full"
                    value={form.data.city}
                    onChange={(e) => form.setData('city', e.target.value)}
                />
                <TextInput
                    placeholder="Address"
                    className="block w-full sm:col-span-2"
                    value={form.data.address}
                    onChange={(e) => form.setData('address', e.target.value)}
                />
                <SelectInput
                    value={form.data.customer_type}
                    onChange={(e) =>
                        form.setData('customer_type', e.target.value)
                    }
                >
                    <option value="cash">Cash</option>
                    <option value="credit">Credit</option>
                </SelectInput>
                {form.data.customer_type === 'credit' && (
                    <TextInput
                        type="number"
                        placeholder="Credit limit"
                        className="block w-full"
                        value={form.data.credit_limit}
                        onChange={(e) =>
                            form.setData('credit_limit', e.target.value)
                        }
                    />
                )}
            </div>
            <div className="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" onClick={onCancel}>
                    Cancel
                </SecondaryButton>
                <PrimaryButton type="submit" disabled={form.processing}>
                    {submitLabel}
                </PrimaryButton>
            </div>
        </form>
    );
}
