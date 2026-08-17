import BiCard from '@/Components/Bi/BiCard';
import BiTable, { BiTableColumn } from '@/Components/Bi/BiTable';
import { router } from '@inertiajs/react';
import { ReactNode } from 'react';

export interface BiPaginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

export default function BiDataGrid<T>({
    title,
    description,
    toolbar,
    columns,
    paginated,
    rowKey,
    emptyMessage,
}: {
    title?: string;
    description?: string;
    toolbar?: ReactNode;
    columns: BiTableColumn<T>[];
    paginated: BiPaginated<T>;
    rowKey: (row: T) => string;
    emptyMessage?: string;
}) {
    return (
        <BiCard title={title} description={description} padded={false}>
            {toolbar && (
                <div className="flex flex-wrap items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-700/60">
                    {toolbar}
                </div>
            )}

            <BiTable
                columns={columns}
                rows={paginated.data}
                rowKey={rowKey}
                emptyMessage={emptyMessage}
            />

            {paginated.meta.links.length > 3 && (
                <div className="flex justify-center gap-1 border-t border-gray-100 px-6 py-4 dark:border-gray-700/60">
                    {paginated.meta.links.map((link, index) => (
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
                            className={`rounded-md px-3 py-1 text-sm ${
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-400 dark:hover:bg-gray-700'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </BiCard>
    );
}
