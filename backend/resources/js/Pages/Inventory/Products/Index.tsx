import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import InventoryLayout from '@/Layouts/InventoryLayout';
import { formatCurrency } from '@/lib/currency';
import { Brand, Category, Product } from '@/types/inventory';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';

interface PaginatedProducts {
    data: Product[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

export default function ProductsIndex({
    products,
    filters,
    categories,
    brands,
}: {
    products: PaginatedProducts;
    filters: Record<string, string>;
    categories: Category[];
    brands: Brand[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const fileInputRef = useRef<HTMLInputElement>(null);

    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('inventory.products.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const importFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        router.post(route('inventory.import.store'), formData);
    };

    return (
        <InventoryLayout title="Products">
            <Card
                title="Products"
                description="Manage your product catalog, pricing and stock."
                actions={
                    <div className="flex gap-2">
                        {/*
                          The template comes first, deliberately. A vendor
                          importing for the first time otherwise guesses
                          the column names, and every row fails on the
                          same mistake — starting from a correct file is
                          the difference between this feature working and
                          generating support tickets.
                        */}
                        <a href={route('inventory.import.template')}>
                            <SecondaryButton type="button">
                                Download template
                            </SecondaryButton>
                        </a>
                        <SecondaryButton
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                        >
                            Import
                        </SecondaryButton>
                        <input
                            ref={fileInputRef}
                            type="file"
                            // CSV stays accepted: a business arriving from
                            // another till usually has one to hand.
                            accept=".xlsx,.xls,.csv"
                            className="hidden"
                            onChange={importFile}
                        />
                        <a href={route('inventory.export.show')}>
                            <SecondaryButton type="button">
                                Export Excel
                            </SecondaryButton>
                        </a>
                        <Link href={route('inventory.products.create')}>
                            <PrimaryButton>New product</PrimaryButton>
                        </Link>
                    </div>
                }
            >
                <form
                    onSubmit={submitSearch}
                    className="mb-4 flex flex-wrap gap-3"
                >
                    <TextInput
                        placeholder="Search name, SKU or barcode…"
                        className="min-w-[240px] flex-1"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <SelectInput
                        value={filters.category_id ?? ''}
                        onChange={(e) =>
                            applyFilters({ category_id: e.target.value })
                        }
                    >
                        <option value="">All categories</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </SelectInput>
                    <SelectInput
                        value={filters.brand_id ?? ''}
                        onChange={(e) =>
                            applyFilters({ brand_id: e.target.value })
                        }
                    >
                        <option value="">All brands</option>
                        {brands.map((brand) => (
                            <option key={brand.id} value={brand.id}>
                                {brand.name}
                            </option>
                        ))}
                    </SelectInput>
                    <SelectInput
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            applyFilters({ status: e.target.value })
                        }
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="archived">Archived</option>
                    </SelectInput>
                    <PrimaryButton type="submit">Search</PrimaryButton>
                </form>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                <th className="py-2 pr-4">Product</th>
                                <th className="py-2 pr-4">SKU</th>
                                <th className="py-2 pr-4">Category</th>
                                <th className="py-2 pr-4">Stock</th>
                                <th className="py-2 pr-4">Price</th>
                                <th className="py-2 pr-4">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                            {products.data.map((product) => (
                                <tr key={product.id}>
                                    <td className="py-3 pr-4">
                                        <Link
                                            href={route(
                                                'inventory.products.show',
                                                product.id,
                                            )}
                                            className="font-medium text-indigo-600 hover:underline"
                                        >
                                            {product.name}
                                        </Link>
                                    </td>
                                    <td className="py-3 pr-4 text-sm text-gray-600 dark:text-gray-400">
                                        {product.sku}
                                    </td>
                                    <td className="py-3 pr-4 text-sm text-gray-600 dark:text-gray-400">
                                        {product.category?.name ?? '—'}
                                    </td>
                                    <td className="py-3 pr-4 text-sm text-gray-600 dark:text-gray-400">
                                        {product.total_quantity ?? '—'}
                                    </td>
                                    <td className="py-3 pr-4 text-sm text-gray-600 dark:text-gray-400">
                                        {formatCurrency(product.selling_price)}
                                    </td>
                                    <td className="py-3 pr-4">
                                        <Badge
                                            variant={
                                                product.status === 'active'
                                                    ? 'success'
                                                    : product.status ===
                                                        'archived'
                                                      ? 'neutral'
                                                      : 'warning'
                                            }
                                        >
                                            {product.status}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {products.data.length === 0 && (
                    <p className="py-8 text-center text-sm text-gray-500">
                        No products yet. Create your first product to get
                        started.
                    </p>
                )}

                <div className="mt-4 flex justify-center gap-2">
                    {products.meta.links.map((link, index) => (
                        <button
                            key={index}
                            disabled={!link.url}
                            onClick={() =>
                                link.url &&
                                router.get(
                                    link.url,
                                    {},
                                    { preserveState: true },
                                )
                            }
                            className={`rounded px-3 py-1 text-sm ${
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-400'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            </Card>
        </InventoryLayout>
    );
}
