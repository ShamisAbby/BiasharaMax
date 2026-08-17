import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import StorefrontLayout, { StorefrontBusiness } from './StorefrontLayout';

interface StorefrontProduct {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    selling_price: string;
    in_stock: boolean;
    category: { id: string; name: string; slug: string } | null;
    images: { path: string; alt_text: string | null }[];
}

export default function StorefrontProducts({
    business,
    products,
    categories,
    filters,
}: {
    business: StorefrontBusiness;
    products: {
        data: StorefrontProduct[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    categories: { id: string; name: string; slug: string }[];
    filters: { search?: string; category?: string };
}) {
    const searchForm = useForm({ search: filters.search ?? '' });

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('public.website.products.index', business.slug), {
            search: searchForm.data.search || undefined,
            category: filters.category || undefined,
        });
    };

    const filterByCategory = (categoryId: string) => {
        router.get(route('public.website.products.index', business.slug), {
            search: filters.search || undefined,
            category: categoryId || undefined,
        });
    };

    return (
        <StorefrontLayout business={business} title="Shop">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <h1 className="text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                    Shop
                </h1>
                <form onSubmit={submitSearch} className="flex gap-2">
                    <input
                        type="text"
                        placeholder="Search products..."
                        value={searchForm.data.search}
                        onChange={(e) =>
                            searchForm.setData('search', e.target.value)
                        }
                        className="rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                    />
                    <button
                        type="submit"
                        className="rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white"
                    >
                        Search
                    </button>
                </form>
            </div>

            {categories.length > 0 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    <button
                        onClick={() => filterByCategory('')}
                        className={`rounded-full px-3 py-1 text-xs font-medium ${!filters.category ? 'bg-[var(--brand-primary)] text-white' : 'bg-[var(--brand-surface)] text-[var(--brand-text)]'}`}
                    >
                        All
                    </button>
                    {categories.map((category) => (
                        <button
                            key={category.id}
                            onClick={() => filterByCategory(category.id)}
                            className={`rounded-full px-3 py-1 text-xs font-medium ${filters.category === category.id ? 'bg-[var(--brand-primary)] text-white' : 'bg-[var(--brand-surface)] text-[var(--brand-text)]'}`}
                        >
                            {category.name}
                        </button>
                    ))}
                </div>
            )}

            <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {products.data.map((product) => (
                    <Link
                        key={product.id}
                        href={route('public.website.products.show', [
                            business.slug,
                            product.slug,
                        ])}
                        className="group overflow-hidden rounded-2xl bg-[var(--brand-surface)] ring-1 ring-black/5 transition hover:shadow-md"
                    >
                        <div className="flex aspect-square items-center justify-center bg-black/5">
                            {product.images[0] ? (
                                <img
                                    src={product.images[0].path}
                                    alt={
                                        product.images[0].alt_text ??
                                        product.name
                                    }
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <span className="text-sm text-[var(--brand-muted)]">
                                    No image
                                </span>
                            )}
                        </div>
                        <div className="p-4">
                            <h3 className="text-base font-[var(--font-heading)] font-semibold text-[var(--brand-text)]">
                                {product.name}
                            </h3>
                            <div className="mt-1 flex items-center justify-between">
                                <span className="text-sm font-bold text-[var(--brand-primary)]">
                                    {product.selling_price}
                                </span>
                                {!product.in_stock && (
                                    <span className="text-xs font-medium text-rose-600">
                                        Out of stock
                                    </span>
                                )}
                            </div>
                        </div>
                    </Link>
                ))}
            </div>

            {products.data.length === 0 && (
                <p className="mt-12 text-center text-sm text-[var(--brand-muted)]">
                    No products found.
                </p>
            )}

            {products.meta.links.length > 3 && (
                <div className="mt-8 flex flex-wrap justify-center gap-1">
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
                            className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-[var(--brand-primary)] text-white' : 'text-[var(--brand-text)]/70 hover:bg-black/5 disabled:opacity-40'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </StorefrontLayout>
    );
}
