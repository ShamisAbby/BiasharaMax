import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import { useConfirm } from '@/Components/ConfirmDialog';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import InventoryLayout from '@/Layouts/InventoryLayout';
import { Attribute } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

function AttributeForm({
    attribute,
    onSaved,
}: {
    attribute?: Attribute;
    onSaved: () => void;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: attribute?.name ?? '',
        input_type: attribute?.input_type ?? 'select',
        is_variant_attribute: attribute?.is_variant_attribute ?? true,
        status: attribute?.status ?? 'active',
        values: attribute?.values?.map((v) => v.value) ?? [],
    });
    const [newValue, setNewValue] = useState('');

    const addValue = () => {
        if (newValue.trim() === '') {
            return;
        }
        setData('values', [...data.values, newValue.trim()]);
        setNewValue('');
    };

    const removeValue = (index: number) => {
        setData(
            'values',
            data.values.filter((_, i) => i !== index),
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { onSuccess: onSaved };

        if (attribute) {
            patch(route('inventory.attributes.update', attribute.id), options);
        } else {
            post(route('inventory.attributes.store'), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel
                    htmlFor="attribute_name"
                    value="Name (e.g. Size, Color)"
                />
                <TextInput
                    id="attribute_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="attribute_input_type" value="Input type" />
                <SelectInput
                    id="attribute_input_type"
                    className="mt-1 block w-full"
                    value={data.input_type}
                    onChange={(e) =>
                        setData(
                            'input_type',
                            e.target.value as 'select' | 'text' | 'number',
                        )
                    }
                >
                    <option value="select">Select from values</option>
                    <option value="text">Free text</option>
                    <option value="number">Number</option>
                </SelectInput>
            </div>

            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    type="checkbox"
                    checked={data.is_variant_attribute}
                    onChange={(e) =>
                        setData('is_variant_attribute', e.target.checked)
                    }
                    className="rounded border-gray-300 text-indigo-600"
                />
                Used to define product variants
            </label>

            {data.input_type === 'select' && (
                <div>
                    <InputLabel value="Values" />
                    <div className="mt-2 flex gap-2">
                        <TextInput
                            placeholder="Add a value"
                            value={newValue}
                            onChange={(e) => setNewValue(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    addValue();
                                }
                            }}
                        />
                        <SecondaryButton type="button" onClick={addValue}>
                            Add
                        </SecondaryButton>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {data.values.map((value, index) => (
                            <span
                                key={index}
                                className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-sm dark:bg-gray-700"
                            >
                                {value}
                                <button
                                    type="button"
                                    onClick={() => removeValue(index)}
                                    className="text-gray-400 hover:text-red-600"
                                >
                                    &times;
                                </button>
                            </span>
                        ))}
                    </div>
                </div>
            )}

            <div>
                <InputLabel htmlFor="attribute_status" value="Status" />
                <SelectInput
                    id="attribute_status"
                    className="mt-1 block w-full"
                    value={data.status}
                    onChange={(e) =>
                        setData(
                            'status',
                            e.target.value as 'active' | 'inactive',
                        )
                    }
                >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </SelectInput>
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>
                    {attribute ? 'Save changes' : 'Create attribute'}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Attributes({
    attributes,
}: {
    attributes: Attribute[];
}) {
    const askConfirm = useConfirm();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Attribute | null>(null);

    const destroy = (attribute: Attribute) => {
        askConfirm({
            title: `Delete the "${attribute.name}" attribute?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('inventory.attributes.destroy', attribute.id),
                );
            },
        });
    };

    return (
        <InventoryLayout title="Attributes">
            <Card
                title="Attributes"
                description="Define attributes like Size or Color used to build product variants."
                actions={
                    <PrimaryButton onClick={() => setCreating(true)}>
                        New attribute
                    </PrimaryButton>
                }
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    {attributes.map((attribute) => (
                        <div
                            key={attribute.id}
                            className="flex items-center justify-between py-3"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {attribute.name}
                                    </p>
                                    {attribute.status === 'inactive' && (
                                        <Badge variant="warning">
                                            Inactive
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {attribute.values
                                        ?.map((v) => v.value)
                                        .join(', ') || 'No values'}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <SecondaryButton
                                    onClick={() => setEditing(attribute)}
                                >
                                    Edit
                                </SecondaryButton>
                                <DangerButton
                                    onClick={() => destroy(attribute)}
                                >
                                    Delete
                                </DangerButton>
                            </div>
                        </div>
                    ))}
                    {attributes.length === 0 && (
                        <p className="py-8 text-center text-sm text-gray-500">
                            No attributes yet.
                        </p>
                    )}
                </div>
            </Card>

            <Modal
                show={creating}
                onClose={() => setCreating(false)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        New attribute
                    </h2>
                    <div className="mt-4">
                        <AttributeForm onSaved={() => setCreating(false)} />
                    </div>
                </div>
            </Modal>

            <Modal
                show={editing !== null}
                onClose={() => setEditing(null)}
                maxWidth="lg"
            >
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Edit attribute
                    </h2>
                    <div className="mt-4">
                        {editing && (
                            <AttributeForm
                                attribute={editing}
                                onSaved={() => setEditing(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </InventoryLayout>
    );
}
