import { Link, router } from '@inertiajs/react';
import StorefrontLayout, { StorefrontBusiness } from './StorefrontLayout';

interface CartLine {
    product_id: string;
    name: string;
    slug: string;
    selling_price: string;
    quantity: number;
    line_total: string;
    image: string | null;
}

export default function StorefrontCart({
    business,
    cart,
}: {
    business: StorefrontBusiness;
    cart: { lines: CartLine[]; subtotal: string };
}) {
    const updateQuantity = (productId: string, quantity: number) => {
        router.patch(
            route('public.website.cart.update', [business.slug, productId]),
            { quantity },
            { preserveScroll: true },
        );
    };

    const removeItem = (productId: string) => {
        router.delete(
            route('public.website.cart.remove', [business.slug, productId]),
            { preserveScroll: true },
        );
    };

    return (
        <StorefrontLayout business={business} title="Your Cart">
            <h1 className="text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                Your Cart
            </h1>

            {cart.lines.length === 0 ? (
                <div className="mt-10 text-center">
                    <p className="text-sm text-[var(--brand-muted)]">
                        Your cart is empty.
                    </p>
                    <Link
                        href={route(
                            'public.website.products.index',
                            business.slug,
                        )}
                        className="mt-4 inline-block rounded-lg bg-[var(--brand-primary)] px-6 py-2.5 text-sm font-semibold text-white"
                    >
                        Continue Shopping
                    </Link>
                </div>
            ) : (
                <div className="mt-8 grid gap-10 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        {cart.lines.map((line) => (
                            <div
                                key={line.product_id}
                                className="flex items-center gap-4 rounded-xl bg-[var(--brand-surface)] p-4"
                            >
                                <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-black/5">
                                    {line.image ? (
                                        <img
                                            src={line.image}
                                            alt={line.name}
                                            className="h-full w-full rounded-lg object-cover"
                                        />
                                    ) : (
                                        <span className="text-xs text-[var(--brand-muted)]">
                                            No image
                                        </span>
                                    )}
                                </div>
                                <div className="flex-1">
                                    <p className="font-medium text-[var(--brand-text)]">
                                        {line.name}
                                    </p>
                                    <p className="text-sm text-[var(--brand-muted)]">
                                        {line.selling_price} each
                                    </p>
                                </div>
                                <input
                                    type="number"
                                    min={0}
                                    max={999}
                                    value={line.quantity}
                                    onChange={(e) =>
                                        updateQuantity(
                                            line.product_id,
                                            Math.max(0, Number(e.target.value)),
                                        )
                                    }
                                    className="w-16 rounded-lg border-gray-300 px-2 py-1.5 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                                />
                                <p className="w-24 text-right font-semibold text-[var(--brand-text)]">
                                    {line.line_total}
                                </p>
                                <button
                                    onClick={() => removeItem(line.product_id)}
                                    className="text-sm text-rose-600 hover:underline"
                                >
                                    Remove
                                </button>
                            </div>
                        ))}
                    </div>

                    <div className="rounded-2xl bg-[var(--brand-surface)] p-6">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-[var(--brand-muted)]">
                                Subtotal
                            </span>
                            <span className="font-semibold text-[var(--brand-text)]">
                                {cart.subtotal}
                            </span>
                        </div>
                        <Link
                            href={route(
                                'public.website.checkout.show',
                                business.slug,
                            )}
                            className="mt-6 block rounded-lg bg-[var(--brand-primary)] px-6 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                        >
                            Proceed to Checkout
                        </Link>
                    </div>
                </div>
            )}
        </StorefrontLayout>
    );
}
