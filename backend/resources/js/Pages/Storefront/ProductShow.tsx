import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import StorefrontLayout, { StorefrontBusiness } from './StorefrontLayout';

interface StorefrontProduct {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    selling_price: string;
    in_stock: boolean;
    available_stock: number | null;
    category: { id: string; name: string; slug: string } | null;
    brand: { id: string; name: string } | null;
    images: { path: string; alt_text: string | null }[];
}

export default function StorefrontProductShow({
    business,
    product,
}: {
    business: StorefrontBusiness;
    product: StorefrontProduct;
}) {
    const [quantity, setQuantity] = useState(1);
    const cartForm = useForm({ product_id: product.id, quantity: 1 });
    const enquiryForm = useForm({
        name: '',
        email: '',
        phone: '',
        message: '',
    });
    const [enquirySent, setEnquirySent] = useState(false);

    const addToCart = (e: FormEvent) => {
        e.preventDefault();
        cartForm.transform(() => ({ product_id: product.id, quantity }));
        cartForm.post(route('public.website.cart.add', business.slug));
    };

    const submitEnquiry = (e: FormEvent) => {
        e.preventDefault();
        enquiryForm.post(
            route('public.website.products.enquiries.store', [
                business.slug,
                product.slug,
            ]),
            {
                onSuccess: () => {
                    setEnquirySent(true);
                    enquiryForm.reset();
                },
            },
        );
    };

    return (
        <StorefrontLayout business={business} title={product.name}>
            <div className="grid gap-10 lg:grid-cols-2">
                <div className="flex aspect-square items-center justify-center rounded-2xl bg-[var(--brand-surface)]">
                    {product.images[0] ? (
                        <img
                            src={product.images[0].path}
                            alt={product.images[0].alt_text ?? product.name}
                            className="h-full w-full rounded-2xl object-cover"
                        />
                    ) : (
                        <span className="text-sm text-[var(--brand-muted)]">
                            No image available
                        </span>
                    )}
                </div>

                <div>
                    {product.category && (
                        <p className="text-sm font-semibold uppercase tracking-wide text-[var(--brand-primary)]">
                            {product.category.name}
                        </p>
                    )}
                    <h1 className="mt-2 text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                        {product.name}
                    </h1>
                    {product.brand && (
                        <p className="mt-1 text-sm text-[var(--brand-muted)]">
                            by {product.brand.name}
                        </p>
                    )}
                    <p className="mt-4 text-2xl font-bold text-[var(--brand-primary)]">
                        {product.selling_price}
                    </p>

                    {product.description && (
                        <p className="mt-4 text-sm leading-relaxed text-[var(--brand-muted)]">
                            {product.description}
                        </p>
                    )}

                    <p className="mt-4 text-sm font-medium">
                        {product.in_stock ? (
                            <span className="text-emerald-600">
                                In stock
                                {product.available_stock !== null
                                    ? ` (${product.available_stock} available)`
                                    : ''}
                            </span>
                        ) : (
                            <span className="text-rose-600">Out of stock</span>
                        )}
                    </p>

                    {product.in_stock && (
                        <form
                            onSubmit={addToCart}
                            className="mt-6 flex items-center gap-3"
                        >
                            <input
                                type="number"
                                min={1}
                                max={999}
                                value={quantity}
                                onChange={(e) =>
                                    setQuantity(
                                        Math.max(1, Number(e.target.value)),
                                    )
                                }
                                className="w-20 rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                            />
                            <button
                                type="submit"
                                disabled={cartForm.processing}
                                className="rounded-lg bg-[var(--brand-primary)] px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                            >
                                Add to Cart
                            </button>
                        </form>
                    )}

                    <div className="mt-10 rounded-2xl bg-[var(--brand-surface)] p-6">
                        <h2 className="text-lg font-[var(--font-heading)] font-semibold text-[var(--brand-text)]">
                            Ask about this product
                        </h2>
                        <p className="mt-1 text-sm text-[var(--brand-muted)]">
                            Have a question? Send us a message and we'll get
                            back to you.
                        </p>

                        {enquirySent ? (
                            <p className="mt-4 text-sm font-medium text-emerald-600">
                                Thanks! Your enquiry has been sent.
                            </p>
                        ) : (
                            <form
                                onSubmit={submitEnquiry}
                                className="mt-4 space-y-3"
                            >
                                <input
                                    placeholder="Your name"
                                    value={enquiryForm.data.name}
                                    onChange={(e) =>
                                        enquiryForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                    className="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                                />
                                {enquiryForm.errors.name && (
                                    <p className="text-xs text-rose-600">
                                        {enquiryForm.errors.name}
                                    </p>
                                )}
                                <div className="grid grid-cols-2 gap-3">
                                    <input
                                        placeholder="Email"
                                        value={enquiryForm.data.email}
                                        onChange={(e) =>
                                            enquiryForm.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        className="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                                    />
                                    <input
                                        placeholder="Phone"
                                        value={enquiryForm.data.phone}
                                        onChange={(e) =>
                                            enquiryForm.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        className="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                                    />
                                </div>
                                <textarea
                                    placeholder="Your question..."
                                    rows={3}
                                    value={enquiryForm.data.message}
                                    onChange={(e) =>
                                        enquiryForm.setData(
                                            'message',
                                            e.target.value,
                                        )
                                    }
                                    className="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                                />
                                {enquiryForm.errors.message && (
                                    <p className="text-xs text-rose-600">
                                        {enquiryForm.errors.message}
                                    </p>
                                )}
                                <button
                                    type="submit"
                                    disabled={enquiryForm.processing}
                                    className="rounded-lg bg-[var(--brand-text)] px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                                >
                                    Send Enquiry
                                </button>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
