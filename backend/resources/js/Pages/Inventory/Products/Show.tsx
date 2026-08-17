import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import { useConfirm } from '@/Components/ConfirmDialog';
import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InventoryLayout from '@/Layouts/InventoryLayout';
import { formatCurrency } from '@/lib/currency';
import { Product } from '@/types/inventory';
import { Link, router } from '@inertiajs/react';

export default function ProductShow({ product }: { product: Product }) {
    const askConfirm = useConfirm();
    const archive = () => {
        askConfirm({
            title: `Archive "${product.name}"? It will be hidden from new sales but stock history is kept.`,
            tone: 'warning',
            confirmLabel: 'Confirm',
            onConfirm: () => {
                router.post(route('inventory.products.archive', product.id));
            },
        });
    };

    const duplicate = () => {
        router.post(route('inventory.products.duplicate', product.id));
    };

    const destroy = () => {
        askConfirm({
            title: `Delete "${product.name}"?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('inventory.products.destroy', product.id));
            },
        });
    };

    return (
        <InventoryLayout title={product.name}>
            <Card
                title={product.name}
                description={`SKU ${product.sku}${product.barcode ? ` · Barcode ${product.barcode}` : ''}`}
                actions={
                    <div className="flex gap-2">
                        <Link
                            href={route('inventory.products.edit', product.id)}
                        >
                            <SecondaryButton type="button">
                                Edit
                            </SecondaryButton>
                        </Link>
                        <SecondaryButton type="button" onClick={duplicate}>
                            Duplicate
                        </SecondaryButton>
                        <SecondaryButton type="button" onClick={archive}>
                            Archive
                        </SecondaryButton>
                        <DangerButton type="button" onClick={destroy}>
                            Delete
                        </DangerButton>
                    </div>
                }
            >
                <div className="grid gap-6 sm:grid-cols-4">
                    <div>
                        <p className="text-xs uppercase text-gray-400">
                            Status
                        </p>
                        <Badge
                            variant={
                                product.status === 'active'
                                    ? 'success'
                                    : 'neutral'
                            }
                        >
                            {product.status}
                        </Badge>
                    </div>
                    <div>
                        <p className="text-xs uppercase text-gray-400">
                            Category
                        </p>
                        <p className="text-sm text-gray-900 dark:text-gray-100">
                            {product.category?.name ?? '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs uppercase text-gray-400">Brand</p>
                        <p className="text-sm text-gray-900 dark:text-gray-100">
                            {product.brand?.name ?? '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs uppercase text-gray-400">
                            Total stock
                        </p>
                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {product.total_quantity ?? '0'}
                        </p>
                    </div>
                </div>

                <div className="mt-6 grid gap-6 sm:grid-cols-3">
                    <div>
                        <p className="text-xs uppercase text-gray-400">
                            Cost price
                        </p>
                        <p className="text-sm text-gray-900 dark:text-gray-100">
                            {formatCurrency(product.cost_price)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs uppercase text-gray-400">
                            Selling price
                        </p>
                        <p className="text-sm text-gray-900 dark:text-gray-100">
                            {formatCurrency(product.selling_price)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs uppercase text-gray-400">
                            Reorder level
                        </p>
                        <p className="text-sm text-gray-900 dark:text-gray-100">
                            {product.reorder_level}
                        </p>
                    </div>
                </div>

                {product.description && (
                    <p className="mt-6 text-sm text-gray-600 dark:text-gray-400">
                        {product.description}
                    </p>
                )}
            </Card>

            <Card title="Stock by warehouse">
                {product.inventories && product.inventories.length > 0 ? (
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                <th className="py-2 pr-4">Warehouse</th>
                                <th className="py-2 pr-4">On hand</th>
                                <th className="py-2 pr-4">Reserved</th>
                                <th className="py-2 pr-4">Available</th>
                                <th className="py-2 pr-4">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                            {product.inventories.map((row) => (
                                <tr key={row.id}>
                                    <td className="py-2 pr-4 text-sm">
                                        {row.warehouse_name}
                                    </td>
                                    <td className="py-2 pr-4 text-sm">
                                        {row.quantity}
                                    </td>
                                    <td className="py-2 pr-4 text-sm">
                                        {row.reserved_quantity}
                                    </td>
                                    <td className="py-2 pr-4 text-sm">
                                        {row.available_quantity}
                                    </td>
                                    <td className="py-2 pr-4">
                                        {row.is_out_of_stock ? (
                                            <Badge variant="danger">
                                                Out of stock
                                            </Badge>
                                        ) : row.is_low_stock ? (
                                            <Badge variant="warning">
                                                Low stock
                                            </Badge>
                                        ) : (
                                            <Badge variant="success">
                                                In stock
                                            </Badge>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <p className="text-sm text-gray-500">
                        No stock recorded yet for this product.
                    </p>
                )}
            </Card>

            {product.product_type === 'variable' && (
                <Card title="Variants">
                    {product.variants && product.variants.length > 0 ? (
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr className="text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    <th className="py-2 pr-4">SKU</th>
                                    <th className="py-2 pr-4">Barcode</th>
                                    <th className="py-2 pr-4">Selling price</th>
                                    <th className="py-2 pr-4">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {product.variants.map((variant) => (
                                    <tr key={variant.id}>
                                        <td className="py-2 pr-4 text-sm">
                                            {variant.sku}
                                        </td>
                                        <td className="py-2 pr-4 text-sm">
                                            {variant.barcode ?? '—'}
                                        </td>
                                        <td className="py-2 pr-4 text-sm">
                                            {variant.selling_price
                                                ? formatCurrency(
                                                      variant.selling_price,
                                                  )
                                                : formatCurrency(
                                                      product.selling_price,
                                                  )}
                                        </td>
                                        <td className="py-2 pr-4">
                                            <Badge
                                                variant={
                                                    variant.status === 'active'
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {variant.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <p className="text-sm text-gray-500">
                            No variants defined.
                        </p>
                    )}
                </Card>
            )}
        </InventoryLayout>
    );
}
