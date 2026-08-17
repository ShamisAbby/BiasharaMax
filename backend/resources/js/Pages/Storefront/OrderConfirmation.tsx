import { CheckCircleIcon } from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';
import StorefrontLayout, { StorefrontBusiness } from './StorefrontLayout';

interface OrderItem {
    product_name: string;
    quantity: string;
    line_total: string;
}

export default function StorefrontOrderConfirmation({
    business,
    order,
}: {
    business: StorefrontBusiness;
    order: {
        sale_number: string;
        total_amount: string;
        payment_status: string;
        delivery_address: string | null;
        items: OrderItem[];
    };
}) {
    return (
        <StorefrontLayout business={business} title="Order Confirmed">
            <div className="mx-auto max-w-xl text-center">
                <CheckCircleIcon className="mx-auto h-14 w-14 text-emerald-500" />
                <h1 className="mt-4 text-2xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                    Thank you for your order!
                </h1>
                <p className="mt-2 text-sm text-[var(--brand-muted)]">
                    Order number {order.sale_number}
                </p>

                <div className="mt-8 rounded-2xl bg-[var(--brand-surface)] p-6 text-left">
                    {order.items.map((item, index) => (
                        <div
                            key={index}
                            className="flex justify-between py-1.5 text-sm"
                        >
                            <span className="text-[var(--brand-muted)]">
                                {item.product_name} x{item.quantity}
                            </span>
                            <span className="font-medium text-[var(--brand-text)]">
                                {item.line_total}
                            </span>
                        </div>
                    ))}
                    <div className="mt-3 flex justify-between border-t border-black/10 pt-3 text-sm font-semibold">
                        <span>Total</span>
                        <span>{order.total_amount}</span>
                    </div>
                    <p className="mt-4 text-sm text-[var(--brand-muted)]">
                        Payment status:{' '}
                        <span className="font-medium capitalize text-[var(--brand-text)]">
                            {order.payment_status}
                        </span>
                    </p>
                    {order.delivery_address && (
                        <p className="mt-1 text-sm text-[var(--brand-muted)]">
                            Delivering to: {order.delivery_address}
                        </p>
                    )}
                </div>

                <Link
                    href={route('public.website.products.index', business.slug)}
                    className="mt-8 inline-block rounded-lg bg-[var(--brand-primary)] px-6 py-2.5 text-sm font-semibold text-white"
                >
                    Continue Shopping
                </Link>
            </div>
        </StorefrontLayout>
    );
}
