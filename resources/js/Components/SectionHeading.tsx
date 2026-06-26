export default function SectionHeading({
    eyebrow,
    title,
    description,
}: {
    eyebrow?: string;
    title: string;
    description?: string;
}) {
    return (
        <div className="mx-auto max-w-2xl text-center">
            {eyebrow && (
                <p className="text-sm font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">
                    {eyebrow}
                </p>
            )}
            <h2 className="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-gray-100">
                {title}
            </h2>
            {description && (
                <p className="mt-4 text-lg text-gray-600 dark:text-gray-400">
                    {description}
                </p>
            )}
        </div>
    );
}
