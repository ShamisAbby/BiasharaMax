import { ReactNode } from 'react';

export interface BiTableColumn<T> {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    render: (row: T) => ReactNode;
}

export default function BiTable<T>({
    columns,
    rows,
    rowKey,
    emptyMessage = 'No records found.',
}: {
    columns: BiTableColumn<T>[];
    rows: T[];
    rowKey: (row: T) => string;
    emptyMessage?: string;
}) {
    const alignClass = (align?: 'left' | 'right' | 'center') =>
        align === 'right'
            ? 'text-right'
            : align === 'center'
              ? 'text-center'
              : 'text-left';

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead className="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                className={`px-4 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 ${alignClass(column.align)}`}
                            >
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {rows.map((row) => (
                        <tr
                            key={rowKey(row)}
                            className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/30"
                        >
                            {columns.map((column) => (
                                <td
                                    key={column.key}
                                    className={`px-4 py-3 text-sm text-gray-700 dark:text-gray-300 ${alignClass(column.align)}`}
                                >
                                    {column.render(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>

            {rows.length === 0 && (
                <p className="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    {emptyMessage}
                </p>
            )}
        </div>
    );
}
