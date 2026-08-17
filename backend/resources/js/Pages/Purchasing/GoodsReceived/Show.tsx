import Card from '@/Components/Card';
import PurchasingLayout from '@/Layouts/PurchasingLayout';
import { GoodsReceivedNote } from '@/types/purchasing';
import { Link } from '@inertiajs/react';

export default function GoodsReceivedShow({
    note,
}: {
    note: GoodsReceivedNote;
}) {
    return (
        <PurchasingLayout title={note.grn_number}>
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <Card title="Purchase Order">
                    {note.purchase_order && (
                        <Link
                            href={route(
                                'purchasing.orders.show',
                                note.purchase_order.id,
                            )}
                            className="font-medium text-indigo-600 hover:underline"
                        >
                            {note.purchase_order.po_number}
                        </Link>
                    )}
                </Card>
                <Card title="Warehouse">
                    <p className="font-medium text-gray-900 dark:text-gray-100">
                        {note.warehouse?.name ?? '—'}
                    </p>
                </Card>
                <Card title="Received By">
                    <p className="font-medium text-gray-900 dark:text-gray-100">
                        {note.received_by?.name ?? '—'}
                    </p>
                </Card>
                <Card title="Received At">
                    <p className="font-medium text-gray-900 dark:text-gray-100">
                        {new Date(note.received_at).toLocaleString()}
                    </p>
                </Card>
            </div>

            <Card title="Items received">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-gray-400">
                            <th className="pb-2 font-medium">Product</th>
                            <th className="pb-2 font-medium">Received</th>
                            <th className="pb-2 font-medium">Damaged</th>
                            <th className="pb-2 font-medium">Rejected</th>
                            <th className="pb-2 font-medium">Batch</th>
                            <th className="pb-2 font-medium">Expiry</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {note.items.map((item) => (
                            <tr key={item.id}>
                                <td className="py-2.5 text-gray-900 dark:text-gray-100">
                                    {item.product?.name ?? '—'}
                                </td>
                                <td className="py-2.5 text-emerald-600">
                                    {item.quantity_received}
                                </td>
                                <td className="py-2.5 text-amber-600">
                                    {item.quantity_damaged}
                                </td>
                                <td className="py-2.5 text-red-600">
                                    {item.quantity_rejected}
                                </td>
                                <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                    {item.batch_number ?? '—'}
                                </td>
                                <td className="py-2.5 text-gray-700 dark:text-gray-300">
                                    {item.expiry_date ?? '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>

            {note.notes && (
                <Card title="Notes">
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        {note.notes}
                    </p>
                </Card>
            )}
        </PurchasingLayout>
    );
}
