import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import StorefrontLayout, { StorefrontBusiness } from './StorefrontLayout';

interface CartLine {
    product_id: string;
    name: string;
    quantity: number;
    line_total: string;
}

type PaymentMethod = 'pay_on_delivery' | 'bank_transfer' | 'mobile_money';

export default function StorefrontCheckout({
    business,
    cart,
}: {
    business: StorefrontBusiness;
    cart: { lines: CartLine[]; subtotal: string };
}) {
    const form = useForm<{
        name: string;
        phone: string;
        email: string;
        delivery_address: string;
        payment_method: PaymentMethod;
        payment_reference: string;
        notes: string;
    }>({
        name: '',
        phone: '',
        email: '',
        delivery_address: '',
        payment_method: 'pay_on_delivery',
        payment_reference: '',
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('public.website.checkout.store', business.slug));
    };

    return (
        <StorefrontLayout business={business} title="Checkout">
            <h1 className="text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                Checkout
            </h1>

            <div className="mt-8 grid gap-10 lg:grid-cols-3">
                <form onSubmit={submit} className="space-y-4 lg:col-span-2">
                    {(form.errors as Record<string, string>).checkout && (
                        <p className="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">
                            {(form.errors as Record<string, string>).checkout}
                        </p>
                    )}

                    <div>
                        <input
                            placeholder="Full name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            className="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                        />
                        {form.errors.name && (
                            <p className="mt-1 text-xs text-rose-600">
                                {form.errors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <input
                                placeholder="Phone number"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                                className="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                            />
                            {form.errors.phone && (
                                <p className="mt-1 text-xs text-rose-600">
                                    {form.errors.phone}
                                </p>
                            )}
                        </div>
                        <input
                            placeholder="Email (optional)"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            className="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                        />
                    </div>

                    <div>
                        <textarea
                            placeholder="Delivery address"
                            rows={2}
                            value={form.data.delivery_address}
                            onChange={(e) =>
                                form.setData('delivery_address', e.target.value)
                            }
                            className="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                        />
                        {form.errors.delivery_address && (
                            <p className="mt-1 text-xs text-rose-600">
                                {form.errors.delivery_address}
                            </p>
                        )}
                    </div>

                    <div>
                        <p className="mb-2 text-sm font-medium text-[var(--brand-text)]">
                            Payment method
                        </p>
                        <div className="space-y-2">
                            {(
                                [
                                    [
                                        'pay_on_delivery',
                                        'Pay on delivery / pickup',
                                    ],
                                    [
                                        'bank_transfer',
                                        "I've already paid by bank transfer",
                                    ],
                                    [
                                        'mobile_money',
                                        "I've already paid by mobile money",
                                    ],
                                ] as [PaymentMethod, string][]
                            ).map(([value, label]) => (
                                <label
                                    key={value}
                                    className="flex items-center gap-2 text-sm text-[var(--brand-text)]"
                                >
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        checked={
                                            form.data.payment_method === value
                                        }
                                        onChange={() =>
                                            form.setData(
                                                'payment_method',
                                                value,
                                            )
                                        }
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                        {form.data.payment_method !== 'pay_on_delivery' && (
                            <input
                                placeholder="Payment reference number"
                                value={form.data.payment_reference}
                                onChange={(e) =>
                                    form.setData(
                                        'payment_reference',
                                        e.target.value,
                                    )
                                }
                                className="mt-3 block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                            />
                        )}
                        {form.errors.payment_reference && (
                            <p className="mt-1 text-xs text-rose-600">
                                {form.errors.payment_reference}
                            </p>
                        )}
                    </div>

                    <textarea
                        placeholder="Order notes (optional)"
                        rows={2}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        className="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                    />

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="w-full rounded-lg bg-[var(--brand-primary)] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                    >
                        Place Order
                    </button>
                </form>

                <div className="rounded-2xl bg-[var(--brand-surface)] p-6">
                    <h2 className="text-lg font-[var(--font-heading)] font-semibold text-[var(--brand-text)]">
                        Order Summary
                    </h2>
                    <div className="mt-4 space-y-2">
                        {cart.lines.map((line) => (
                            <div
                                key={line.product_id}
                                className="flex justify-between text-sm"
                            >
                                <span className="text-[var(--brand-muted)]">
                                    {line.name} x{line.quantity}
                                </span>
                                <span className="font-medium text-[var(--brand-text)]">
                                    {line.line_total}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 flex justify-between border-t border-black/10 pt-4 text-sm font-semibold">
                        <span>Total</span>
                        <span>{cart.subtotal}</span>
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
