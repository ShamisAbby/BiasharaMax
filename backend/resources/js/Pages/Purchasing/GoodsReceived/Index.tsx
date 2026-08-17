import Badge from '@/Components/Badge';
import PurchasingLayout from '@/Layouts/PurchasingLayout';
import { GoodsReceivedNote } from '@/types/purchasing';
import { Link, router } from '@inertiajs/react';

const STATUS_VARIANT: Record<
    string,
    'neutral' | 'success' | 'warning' | 'danger' | 'info'
> = {
    fully_received: 'success',
    partially_received: 'warning',
};

export default function GoodsReceivedIndex({
    notes,
}: {
    notes: {
        data: GoodsReceivedNote[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
}) {
    return (
        <PurchasingLayout title="Goods Received">
            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {[
                                'GRN Number',
                                'Purchase Order',
                                'Received At',
                                'PO Status',
                                '',
                            ].map((h) => (
                                <th
                                    key={h}
                                    className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {notes.data.map((note) => (
                            <tr
                                key={note.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {note.grn_number}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {note.purchase_order && (
                                        <Link
                                            href={route(
                                                'purchasing.orders.show',
                                                note.purchase_order.id,
                                            )}
                                            className="text-indigo-600 hover:underline"
                                        >
                                            {note.purchase_order.po_number}
                                        </Link>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {new Date(
                                        note.received_at,
                                    ).toLocaleString()}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    {note.purchase_order && (
                                        <Badge
                                            variant={
                                                STATUS_VARIANT[
                                                    note.purchase_order.status
                                                ] ?? 'neutral'
                                            }
                                        >
                                            {note.purchase_order.status.replace(
                                                '_',
                                                ' ',
                                            )}
                                        </Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <Link
                                        href={route(
                                            'purchasing.goods-received.show',
                                            note.id,
                                        )}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {notes.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No deliveries recorded yet.
                    </p>
                )}

                {notes.meta.links.length > 3 && (
                    <div className="flex flex-wrap gap-1 border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                        {notes.meta.links.map((link, index) => (
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
                                        : 'text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-gray-800'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </PurchasingLayout>
    );
}
