import Card from '@/Components/Card';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InventoryLayout from '@/Layouts/InventoryLayout';
import { Tag } from '@/types/inventory';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Tags({ tags }: { tags: Tag[] }) {
    const askConfirm = useConfirm();
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('inventory.tags.store'), { onSuccess: () => reset() });
    };

    const destroy = (tag: Tag) => {
        askConfirm({
            title: `Delete the "${tag.name}" tag?`,
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('inventory.tags.destroy', tag.id));
            },
        });
    };

    return (
        <InventoryLayout title="Tags">
            <Card
                title="Tags"
                description="Freeform labels you can attach to products for quick filtering."
            >
                <form onSubmit={submit} className="mb-6 flex gap-3">
                    <div className="flex-1">
                        <TextInput
                            placeholder="New tag name"
                            className="block w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>
                    <PrimaryButton disabled={processing}>Add tag</PrimaryButton>
                </form>

                <div className="flex flex-wrap gap-2">
                    {tags.map((tag) => (
                        <span
                            key={tag.id}
                            className="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                        >
                            {tag.name}
                            <button
                                type="button"
                                onClick={() => destroy(tag)}
                                className="text-gray-400 hover:text-red-600"
                                aria-label={`Delete ${tag.name}`}
                            >
                                &times;
                            </button>
                        </span>
                    ))}
                    {tags.length === 0 && (
                        <p className="text-sm text-gray-500">No tags yet.</p>
                    )}
                </div>
            </Card>
        </InventoryLayout>
    );
}
