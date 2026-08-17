import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import { Sale } from '@/types/sales';
import { Head, Link } from '@inertiajs/react';

export default function POSReceipt({ sale }: { sale: Sale }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Sale Complete
                </h2>
            }
        >
            <Head title={`Receipt — ${sale.sale_number}`} />

            <div className="mx-auto max-w-md px-4 py-8">
                <div
                    className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                    id="receipt"
                >
                    <div className="text-center">
                        <p className="text-lg font-bold text-gray-900 dark:text-gray-100">
                            Receipt
                        </p>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {sale.sale_number}
                        </p>
                        <p className="text-xs text-gray-400">
                            {new Date(sale.created_at).toLocaleString()}
                        </p>
                    </div>

                    <div className="mt-4 divide-y divide-gray-100 border-t border-gray-100 text-sm dark:divide-gray-700 dark:border-gray-700">
                        {sale.items.map((item) => (
                            <div
                                key={item.id}
                                className="flex justify-between py-2"
                            >
                                <span className="text-gray-700 dark:text-gray-300">
                                    {item.product_name} × {item.quantity}
                                </span>
                                <span className="text-gray-900 dark:text-gray-100">
                                    {formatCurrency(item.line_total)}
                                </span>
                            </div>
                        ))}
                    </div>

                    <div className="mt-2 space-y-1 border-t border-gray-100 pt-2 text-sm dark:border-gray-700">
                        <div className="flex justify-between text-gray-500 dark:text-gray-400">
                            <span>Subtotal</span>
                            <span>{formatCurrency(sale.subtotal)}</span>
                        </div>
                        <div className="flex justify-between text-gray-500 dark:text-gray-400">
                            <span>Tax</span>
                            <span>{formatCurrency(sale.tax_amount)}</span>
                        </div>
                        <div className="flex justify-between text-base font-bold text-gray-900 dark:text-gray-100">
                            <span>Total</span>
                            <span>{formatCurrency(sale.total_amount)}</span>
                        </div>
                        <div className="flex justify-between text-gray-500 dark:text-gray-400">
                            <span>Paid</span>
                            <span>{formatCurrency(sale.paid_amount)}</span>
                        </div>
                        {sale.balance_due !== '0.00' && (
                            <div className="flex justify-between font-medium text-amber-600">
                                <span>Balance Due</span>
                                <span>{formatCurrency(sale.balance_due)}</span>
                            </div>
                        )}
                    </div>

                    {sale.customer && (
                        <p className="mt-4 text-center text-xs text-gray-400">
                            Customer: {sale.customer.name}
                        </p>
                    )}
                </div>

                <div className="mt-6 flex gap-3 print:hidden">
                    <SecondaryButton
                        className="flex-1 justify-center"
                        onClick={() => window.print()}
                    >
                        Print
                    </SecondaryButton>
                    <Link href={route('pos.terminal')} className="flex-1">
                        <PrimaryButton className="w-full justify-center">
                            New Sale
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
