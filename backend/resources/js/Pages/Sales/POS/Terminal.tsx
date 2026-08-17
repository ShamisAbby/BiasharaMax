import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import { Customer, POSProduct } from '@/types/sales';
import {
    MagnifyingGlassIcon,
    MinusIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface CartLine {
    product: POSProduct;
    quantity: number;
}

interface Branch {
    id: string;
    name: string;
    is_main: boolean;
}

interface Warehouse {
    id: string;
    name: string;
    branch_id: string;
    is_default: boolean;
}

export default function POSTerminal({
    products,
    branches,
    warehouses,
    customers,
    defaultBranchId,
    defaultWarehouseId,
}: {
    products: POSProduct[];
    branches: Branch[];
    warehouses: Warehouse[];
    customers: Customer[];
    defaultBranchId: string | null;
    defaultWarehouseId: string | null;
}) {
    const [search, setSearch] = useState('');
    const [cart, setCart] = useState<CartLine[]>([]);

    const form = useForm({
        branch_id: defaultBranchId ?? '',
        warehouse_id: defaultWarehouseId ?? '',
        customer_id: '',
        items: [] as { product_id: string; quantity: number }[],
        payments: [] as { amount: string; payment_method: string }[],
        amount_tendered: '',
        payment_method: 'cash',
    });

    const filteredProducts = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return products;

        return products.filter(
            (p) =>
                p.name.toLowerCase().includes(term) ||
                p.sku?.toLowerCase().includes(term) ||
                p.barcode?.toLowerCase().includes(term),
        );
    }, [search, products]);

    const addToCart = (product: POSProduct) => {
        setCart((prev) => {
            const existing = prev.find(
                (line) => line.product.id === product.id,
            );
            if (existing) {
                return prev.map((line) =>
                    line.product.id === product.id
                        ? { ...line, quantity: line.quantity + 1 }
                        : line,
                );
            }
            return [...prev, { product, quantity: 1 }];
        });
    };

    const updateQuantity = (productId: string, delta: number) => {
        setCart((prev) =>
            prev
                .map((line) =>
                    line.product.id === productId
                        ? { ...line, quantity: line.quantity + delta }
                        : line,
                )
                .filter((line) => line.quantity > 0),
        );
    };

    const removeFromCart = (productId: string) => {
        setCart((prev) => prev.filter((line) => line.product.id !== productId));
    };

    const subtotal = cart.reduce(
        (sum, line) => sum + Number(line.product.selling_price) * line.quantity,
        0,
    );
    const taxTotal = cart.reduce(
        (sum, line) =>
            sum +
            Number(line.product.selling_price) *
                line.quantity *
                (Number(line.product.tax_rate) / 100),
        0,
    );
    const total = subtotal + taxTotal;

    const selectedCustomer = customers.find(
        (c) => c.id === form.data.customer_id,
    );
    const amountTendered = Number(form.data.amount_tendered || 0);
    const changeDue = amountTendered - total;

    const selectBranch = (branchId: string) => {
        const defaultWarehouse =
            warehouses.find((w) => w.branch_id === branchId && w.is_default) ??
            warehouses.find((w) => w.branch_id === branchId);
        form.setData({
            ...form.data,
            branch_id: branchId,
            warehouse_id: defaultWarehouse?.id ?? '',
        });
    };

    const checkout = () => {
        if (cart.length === 0) return;

        const payments =
            amountTendered > 0
                ? [
                      {
                          amount: String(Math.min(amountTendered, total)),
                          payment_method: form.data.payment_method,
                      },
                  ]
                : [];

        form.transform((data) => ({
            branch_id: data.branch_id,
            warehouse_id: data.warehouse_id,
            customer_id: data.customer_id || undefined,
            items: cart.map((line) => ({
                product_id: line.product.id,
                quantity: line.quantity,
            })),
            payments,
        }));

        form.post(route('pos.checkout'));
    };

    const branchWarehouses = warehouses.filter(
        (w) => w.branch_id === form.data.branch_id,
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        POS Terminal
                    </h2>
                    <Link
                        href={route('sales.dashboard')}
                        className="text-sm text-indigo-600 hover:underline"
                    >
                        Back to Sales
                    </Link>
                </div>
            }
        >
            <Head title="POS Terminal" />

            <div className="grid h-[calc(100vh-9rem)] grid-cols-1 gap-4 px-4 py-4 lg:grid-cols-3 lg:px-6">
                <div className="flex flex-col lg:col-span-2">
                    <div className="relative mb-4">
                        <MagnifyingGlassIcon className="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                        <TextInput
                            autoFocus
                            placeholder="Search products by name, SKU, or barcode..."
                            className="block w-full pl-10"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <div className="grid flex-1 auto-rows-max grid-cols-2 gap-3 overflow-y-auto pb-2 sm:grid-cols-3 lg:grid-cols-4">
                        {filteredProducts.map((product) => {
                            const outOfStock = product.stock_on_hand <= 0;
                            const inCart = cart.find(
                                (l) => l.product.id === product.id,
                            );
                            const cartQty = inCart?.quantity ?? 0;

                            return (
                                <button
                                    key={product.id}
                                    type="button"
                                    onClick={() => addToCart(product)}
                                    className={`group relative flex flex-col rounded-xl border text-left transition-all duration-150 ${
                                        inCart
                                            ? 'border-indigo-400 bg-indigo-600 shadow-lg shadow-indigo-500/20 dark:border-indigo-500 dark:bg-indigo-600'
                                            : outOfStock
                                              ? 'border-amber-200/60 bg-gray-800/60 hover:border-amber-300 dark:border-amber-800/40 dark:bg-gray-800/60'
                                              : 'hover:bg-gray-750 border-gray-700/50 bg-gray-800 hover:border-indigo-500 hover:shadow-lg hover:shadow-indigo-500/10 dark:border-gray-700/50 dark:bg-gray-800'
                                    }`}
                                >
                                    {/* Colour accent top bar */}
                                    <div
                                        className={`h-1 w-full rounded-t-xl ${inCart ? 'bg-indigo-400' : outOfStock ? 'bg-amber-400/50' : 'bg-indigo-500/30 group-hover:bg-indigo-500/70'} transition-colors`}
                                    />

                                    <div className="flex flex-1 flex-col p-3">
                                        {/* Name */}
                                        <p
                                            className={`line-clamp-2 text-sm font-semibold leading-snug ${inCart ? 'text-white' : 'text-gray-100'}`}
                                        >
                                            {product.name}
                                        </p>

                                        {/* SKU */}
                                        <p
                                            className={`mt-0.5 text-[11px] ${inCart ? 'text-indigo-200' : 'text-gray-500'}`}
                                        >
                                            {product.sku}
                                        </p>

                                        {/* Spacer */}
                                        <div className="flex-1" />

                                        {/* Price row */}
                                        <div className="mt-3 flex items-end justify-between gap-1">
                                            <span
                                                className={`text-base font-bold ${inCart ? 'text-white' : 'text-indigo-400'}`}
                                            >
                                                {formatCurrency(
                                                    product.selling_price,
                                                )}
                                            </span>
                                            {inCart ? (
                                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-xs font-bold text-white">
                                                    {cartQty}
                                                </span>
                                            ) : outOfStock ? (
                                                <span className="rounded-md bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-amber-400">
                                                    No stock
                                                </span>
                                            ) : (
                                                <span className="text-[11px] text-gray-500">
                                                    {product.stock_on_hand} left
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </button>
                            );
                        })}

                        {filteredProducts.length === 0 && (
                            <p className="col-span-full py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                No products found.
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex flex-col rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <div className="flex-1 overflow-y-auto p-4">
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Cart
                        </h3>

                        {cart.length === 0 ? (
                            <p className="mt-6 text-center text-sm text-gray-400">
                                Tap a product to add it to the cart.
                            </p>
                        ) : (
                            <div className="mt-3 space-y-3">
                                {cart.map((line) => {
                                    const noStock =
                                        line.product.stock_on_hand <= 0;
                                    const overStock =
                                        line.quantity >
                                            line.product.stock_on_hand &&
                                        !noStock;
                                    return (
                                        <div
                                            key={line.product.id}
                                            className={`rounded-lg ${noStock ? 'bg-amber-50 px-2 py-1 ring-1 ring-amber-200 dark:bg-amber-900/10 dark:ring-amber-800/40' : ''}`}
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {line.product.name}
                                                    </p>
                                                    <p className="text-xs text-gray-400">
                                                        {formatCurrency(
                                                            line.product
                                                                .selling_price,
                                                        )}{' '}
                                                        each
                                                    </p>
                                                    {noStock && (
                                                        <p className="text-xs text-amber-600 dark:text-amber-400">
                                                            ⚠ No stock recorded
                                                            — add stock in
                                                            Inventory first
                                                        </p>
                                                    )}
                                                    {overStock && (
                                                        <p className="text-xs text-amber-600 dark:text-amber-400">
                                                            ⚠ Only{' '}
                                                            {
                                                                line.product
                                                                    .stock_on_hand
                                                            }{' '}
                                                            available
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <button
                                                        onClick={() =>
                                                            updateQuantity(
                                                                line.product.id,
                                                                -1,
                                                            )
                                                        }
                                                        className="rounded p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                    >
                                                        <MinusIcon className="h-4 w-4" />
                                                    </button>
                                                    <span className="w-6 text-center text-sm">
                                                        {line.quantity}
                                                    </span>
                                                    <button
                                                        onClick={() =>
                                                            updateQuantity(
                                                                line.product.id,
                                                                1,
                                                            )
                                                        }
                                                        className="rounded p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                    >
                                                        <PlusIcon className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        onClick={() =>
                                                            removeFromCart(
                                                                line.product.id,
                                                            )
                                                        }
                                                        className="ml-1 rounded p-1 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    >
                                                        <TrashIcon className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        <div className="mt-6 space-y-3 border-t border-gray-100 pt-4 dark:border-gray-700">
                            {branches.length > 1 && (
                                <SelectInput
                                    className="block w-full"
                                    value={form.data.branch_id}
                                    onChange={(e) =>
                                        selectBranch(e.target.value)
                                    }
                                >
                                    {branches.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.name}
                                        </option>
                                    ))}
                                </SelectInput>
                            )}
                            {branchWarehouses.length > 1 && (
                                <SelectInput
                                    className="block w-full"
                                    value={form.data.warehouse_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'warehouse_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {branchWarehouses.map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name}
                                        </option>
                                    ))}
                                </SelectInput>
                            )}

                            <SelectInput
                                className="block w-full"
                                value={form.data.customer_id}
                                onChange={(e) =>
                                    form.setData('customer_id', e.target.value)
                                }
                            >
                                <option value="">
                                    Walk-in customer (cash)
                                </option>
                                {customers.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}{' '}
                                        {c.customer_type === 'credit'
                                            ? `(credit: ${formatCurrency(c.available_credit)} available)`
                                            : ''}
                                    </option>
                                ))}
                            </SelectInput>
                            {selectedCustomer?.customer_type === 'credit' &&
                                Number(selectedCustomer.current_balance) >
                                    0 && (
                                    <p className="text-xs text-amber-600">
                                        Current balance owed:{' '}
                                        {formatCurrency(
                                            selectedCustomer.current_balance,
                                        )}
                                    </p>
                                )}
                        </div>
                    </div>

                    <div className="border-t border-gray-100 p-4 dark:border-gray-700">
                        <div className="space-y-1 text-sm">
                            <div className="flex justify-between text-gray-500 dark:text-gray-400">
                                <span>Subtotal</span>
                                <span>{formatCurrency(subtotal)}</span>
                            </div>
                            <div className="flex justify-between text-gray-500 dark:text-gray-400">
                                <span>Tax</span>
                                <span>{formatCurrency(taxTotal)}</span>
                            </div>
                            <div className="flex justify-between text-base font-bold text-gray-900 dark:text-gray-100">
                                <span>Total</span>
                                <span>{formatCurrency(total)}</span>
                            </div>
                        </div>

                        <div className="mt-4 grid grid-cols-2 gap-2">
                            <SelectInput
                                value={form.data.payment_method}
                                onChange={(e) =>
                                    form.setData(
                                        'payment_method',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="cash">Cash</option>
                                <option value="mobile_money">
                                    Mobile Money
                                </option>
                                <option value="card">Card</option>
                                <option value="bank_transfer">
                                    Bank Transfer
                                </option>
                            </SelectInput>
                            <TextInput
                                type="number"
                                step="0.01"
                                placeholder="Amount tendered"
                                value={form.data.amount_tendered}
                                onChange={(e) =>
                                    form.setData(
                                        'amount_tendered',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        {amountTendered > 0 && (
                            <p
                                className={`mt-2 text-sm ${changeDue >= 0 ? 'text-emerald-600' : 'text-amber-600'}`}
                            >
                                {changeDue >= 0
                                    ? `Change due: ${formatCurrency(changeDue)}`
                                    : `Remaining balance: ${formatCurrency(Math.abs(changeDue))} (credit sale)`}
                            </p>
                        )}

                        <InputError
                            message={form.errors.items}
                            className="mt-2"
                        />

                        <PrimaryButton
                            className="mt-4 w-full justify-center py-3 text-sm"
                            disabled={cart.length === 0 || form.processing}
                            onClick={checkout}
                        >
                            Complete Sale — {formatCurrency(total)}
                        </PrimaryButton>
                        {cart.length > 0 && (
                            <SecondaryButton
                                className="mt-2 w-full justify-center"
                                onClick={() => setCart([])}
                            >
                                Clear Cart
                            </SecondaryButton>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
