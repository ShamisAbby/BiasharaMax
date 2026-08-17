import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import InventoryLayout from '@/Layouts/InventoryLayout';
import { Brand, Category, Product, Supplier, Unit } from '@/types/inventory';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface VariantInput {
    id?: string;
    sku: string;
    barcode: string;
    cost_price: string;
    selling_price: string;
    status: string;
}

export default function ProductForm({
    product,
    categories,
    brands,
    units,
    suppliers,
}: {
    product?: Product;
    categories: Category[];
    brands: Brand[];
    units: Unit[];
    suppliers: Supplier[];
}) {
    const isEditing = !!product;

    const { data, setData, post, patch, processing, errors } = useForm({
        category_id: product?.category?.id ?? '',
        brand_id: product?.brand?.id ?? '',
        unit_id: product?.unit?.id ?? '',
        default_supplier_id: product?.default_supplier?.id ?? '',
        name: product?.name ?? '',
        sku: product?.sku ?? '',
        barcode: product?.barcode ?? '',
        description: product?.description ?? '',
        product_type: product?.product_type ?? 'simple',
        track_stock: product?.track_stock ?? true,
        has_expiry: product?.has_expiry ?? false,
        has_batch: product?.has_batch ?? false,
        has_serial: product?.has_serial ?? false,
        cost_price: product?.cost_price ?? '0',
        selling_price: product?.selling_price ?? '0',
        wholesale_price: product?.wholesale_price ?? '',
        tax_rate: product?.tax_rate ?? '0',
        minimum_stock: product?.minimum_stock ?? '0',
        reorder_level: product?.reorder_level ?? '0',
        status: product?.status ?? 'active',
        visibility: product?.visibility ?? 'visible',
        tag_ids: product?.tags?.map((t) => t.id) ?? [],
        collection_ids: product?.collections?.map((c) => c.id) ?? [],
        supplier_ids: product?.suppliers?.map((s) => s.id) ?? [],
        variants: (product?.variants ?? []).map((v) => ({
            id: v.id,
            sku: v.sku,
            barcode: v.barcode ?? '',
            cost_price: v.cost_price ?? '',
            selling_price: v.selling_price ?? '',
            status: v.status,
        })) as VariantInput[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEditing) {
            patch(route('inventory.products.update', product!.id));
        } else {
            post(route('inventory.products.store'));
        }
    };

    const addVariant = () => {
        setData('variants', [
            ...data.variants,
            {
                sku: '',
                barcode: '',
                cost_price: '',
                selling_price: '',
                status: 'active',
            },
        ]);
    };

    const updateVariant = (
        index: number,
        field: keyof VariantInput,
        value: string,
    ) => {
        const variants = [...data.variants];
        variants[index] = { ...variants[index], [field]: value };
        setData('variants', variants);
    };

    const removeVariant = (index: number) => {
        setData(
            'variants',
            data.variants.filter((_, i) => i !== index),
        );
    };

    return (
        <InventoryLayout title={isEditing ? 'Edit product' : 'New product'}>
            <form onSubmit={submit} className="space-y-6">
                <Card title="Basic information">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="name" value="Product name" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                            <InputError
                                message={errors.name}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="sku"
                                value="SKU (leave blank to auto-generate)"
                            />
                            <TextInput
                                id="sku"
                                className="mt-1 block w-full"
                                value={data.sku}
                                onChange={(e) => setData('sku', e.target.value)}
                            />
                            <InputError message={errors.sku} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="barcode" value="Barcode" />
                            <TextInput
                                id="barcode"
                                className="mt-1 block w-full"
                                value={data.barcode}
                                onChange={(e) =>
                                    setData('barcode', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.barcode}
                                className="mt-2"
                            />
                        </div>

                        <div className="sm:col-span-2">
                            <InputLabel
                                htmlFor="description"
                                value="Description"
                            />
                            <textarea
                                id="description"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                rows={3}
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                        </div>
                    </div>
                </Card>

                <Card title="Classification">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel
                                htmlFor="category_id"
                                value="Category"
                            />
                            <SelectInput
                                id="category_id"
                                className="mt-1 block w-full"
                                value={data.category_id}
                                onChange={(e) =>
                                    setData('category_id', e.target.value)
                                }
                            >
                                <option value="">None</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>

                        <div>
                            <InputLabel htmlFor="brand_id" value="Brand" />
                            <SelectInput
                                id="brand_id"
                                className="mt-1 block w-full"
                                value={data.brand_id}
                                onChange={(e) =>
                                    setData('brand_id', e.target.value)
                                }
                            >
                                <option value="">None</option>
                                {brands.map((brand) => (
                                    <option key={brand.id} value={brand.id}>
                                        {brand.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="unit_id"
                                value="Unit of measurement"
                            />
                            <SelectInput
                                id="unit_id"
                                className="mt-1 block w-full"
                                value={data.unit_id}
                                onChange={(e) =>
                                    setData('unit_id', e.target.value)
                                }
                            >
                                <option value="">None</option>
                                {units.map((unit) => (
                                    <option key={unit.id} value={unit.id}>
                                        {unit.name} ({unit.symbol})
                                    </option>
                                ))}
                            </SelectInput>
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="default_supplier_id"
                                value="Default supplier"
                            />
                            <SelectInput
                                id="default_supplier_id"
                                className="mt-1 block w-full"
                                value={data.default_supplier_id}
                                onChange={(e) =>
                                    setData(
                                        'default_supplier_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">None</option>
                                {suppliers.map((supplier) => (
                                    <option
                                        key={supplier.id}
                                        value={supplier.id}
                                    >
                                        {supplier.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="product_type"
                                value="Product type"
                            />
                            <SelectInput
                                id="product_type"
                                className="mt-1 block w-full"
                                value={data.product_type}
                                onChange={(e) =>
                                    setData(
                                        'product_type',
                                        e.target.value as
                                            | 'simple'
                                            | 'variable'
                                            | 'service',
                                    )
                                }
                            >
                                <option value="simple">Simple</option>
                                <option value="variable">
                                    Variable (has variants)
                                </option>
                                <option value="service">
                                    Service (no stock)
                                </option>
                            </SelectInput>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-6">
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={data.track_stock}
                                onChange={(e) =>
                                    setData('track_stock', e.target.checked)
                                }
                                className="rounded border-gray-300 text-indigo-600"
                            />
                            Track stock
                        </label>
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={data.has_expiry}
                                onChange={(e) =>
                                    setData('has_expiry', e.target.checked)
                                }
                                className="rounded border-gray-300 text-indigo-600"
                            />
                            Has expiry date
                        </label>
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={data.has_batch}
                                onChange={(e) =>
                                    setData('has_batch', e.target.checked)
                                }
                                className="rounded border-gray-300 text-indigo-600"
                            />
                            Has batch tracking
                        </label>
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={data.has_serial}
                                onChange={(e) =>
                                    setData('has_serial', e.target.checked)
                                }
                                className="rounded border-gray-300 text-indigo-600"
                            />
                            Has serial numbers
                        </label>
                    </div>
                </Card>

                <Card title="Pricing & stock">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel
                                htmlFor="cost_price"
                                value="Cost price"
                            />
                            <TextInput
                                id="cost_price"
                                type="number"
                                step="0.01"
                                className="mt-1 block w-full"
                                value={data.cost_price}
                                onChange={(e) =>
                                    setData('cost_price', e.target.value)
                                }
                                required
                            />
                            <InputError
                                message={errors.cost_price}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="selling_price"
                                value="Selling price"
                            />
                            <TextInput
                                id="selling_price"
                                type="number"
                                step="0.01"
                                className="mt-1 block w-full"
                                value={data.selling_price}
                                onChange={(e) =>
                                    setData('selling_price', e.target.value)
                                }
                                required
                            />
                            <InputError
                                message={errors.selling_price}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="wholesale_price"
                                value="Wholesale price"
                            />
                            <TextInput
                                id="wholesale_price"
                                type="number"
                                step="0.01"
                                className="mt-1 block w-full"
                                value={data.wholesale_price}
                                onChange={(e) =>
                                    setData('wholesale_price', e.target.value)
                                }
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="tax_rate"
                                value="Tax rate (%)"
                            />
                            <TextInput
                                id="tax_rate"
                                type="number"
                                step="0.01"
                                className="mt-1 block w-full"
                                value={data.tax_rate}
                                onChange={(e) =>
                                    setData('tax_rate', e.target.value)
                                }
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="minimum_stock"
                                value="Minimum stock"
                            />
                            <TextInput
                                id="minimum_stock"
                                type="number"
                                step="0.001"
                                className="mt-1 block w-full"
                                value={data.minimum_stock}
                                onChange={(e) =>
                                    setData('minimum_stock', e.target.value)
                                }
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="reorder_level"
                                value="Reorder level"
                            />
                            <TextInput
                                id="reorder_level"
                                type="number"
                                step="0.001"
                                className="mt-1 block w-full"
                                value={data.reorder_level}
                                onChange={(e) =>
                                    setData('reorder_level', e.target.value)
                                }
                            />
                        </div>
                    </div>
                </Card>

                {data.product_type === 'variable' && (
                    <Card
                        title="Variants"
                        description="Each variant can override price and has its own SKU/barcode."
                        actions={
                            <SecondaryButton type="button" onClick={addVariant}>
                                Add variant
                            </SecondaryButton>
                        }
                    >
                        <div className="space-y-3">
                            {data.variants.map((variant, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 sm:grid-cols-5"
                                >
                                    <TextInput
                                        placeholder="SKU"
                                        value={variant.sku}
                                        onChange={(e) =>
                                            updateVariant(
                                                index,
                                                'sku',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <TextInput
                                        placeholder="Barcode"
                                        value={variant.barcode}
                                        onChange={(e) =>
                                            updateVariant(
                                                index,
                                                'barcode',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        placeholder="Cost price"
                                        value={variant.cost_price}
                                        onChange={(e) =>
                                            updateVariant(
                                                index,
                                                'cost_price',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        placeholder="Selling price"
                                        value={variant.selling_price}
                                        onChange={(e) =>
                                            updateVariant(
                                                index,
                                                'selling_price',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => removeVariant(index)}
                                    >
                                        Remove
                                    </SecondaryButton>
                                </div>
                            ))}
                            {data.variants.length === 0 && (
                                <p className="text-sm text-gray-500">
                                    No variants yet. Add at least one.
                                </p>
                            )}
                        </div>
                    </Card>
                )}

                <Card title="Status">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="status" value="Status" />
                            <SelectInput
                                id="status"
                                className="mt-1 block w-full"
                                value={data.status}
                                onChange={(e) =>
                                    setData(
                                        'status',
                                        e.target.value as
                                            | 'active'
                                            | 'inactive'
                                            | 'archived',
                                    )
                                }
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="archived">Archived</option>
                            </SelectInput>
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="visibility"
                                value="Visibility"
                            />
                            <SelectInput
                                id="visibility"
                                className="mt-1 block w-full"
                                value={data.visibility}
                                onChange={(e) =>
                                    setData(
                                        'visibility',
                                        e.target.value as 'visible' | 'hidden',
                                    )
                                }
                            >
                                <option value="visible">Visible</option>
                                <option value="hidden">Hidden</option>
                            </SelectInput>
                        </div>
                    </div>
                </Card>

                <div className="flex justify-end gap-3">
                    <PrimaryButton disabled={processing}>
                        {isEditing ? 'Save changes' : 'Create product'}
                    </PrimaryButton>
                </div>
            </form>
        </InventoryLayout>
    );
}
