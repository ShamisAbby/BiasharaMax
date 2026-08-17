import { ReactNode } from 'react';

export default function BiTopbar({
    left,
    right,
}: {
    left?: ReactNode;
    right?: ReactNode;
}) {
    return (
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-4 border-b border-gray-200 bg-white/90 px-4 backdrop-blur-md dark:border-gray-700 dark:bg-gray-800/90 sm:px-6">
            <div className="flex flex-1 items-center gap-3">{left}</div>
            <div className="flex items-center gap-1">{right}</div>
        </header>
    );
}
