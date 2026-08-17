/**
 * The heading block above a landing-page section.
 *
 * The sizes here are the same ones `TYPE` uses in Welcome.tsx — this
 * component and the sections that set their own headings have to agree,
 * or the page has two heading sizes again, which is the drift this was
 * meant to end. If one changes, change both.
 *
 * Kept as literal classes rather than importing TYPE from the page: a
 * shared component reaching into a page for its styling inverts the
 * dependency, and Tailwind must see the class strings written out to
 * include them in the build.
 */
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
                <p className="text-sm font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                    {eyebrow}
                </p>
            )}
            <h2 className="mt-3 text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100 sm:text-4xl lg:text-5xl">
                {title}
            </h2>
            {description && (
                <p className="mt-4 text-lg leading-relaxed text-gray-600 dark:text-gray-400 lg:text-xl">
                    {description}
                </p>
            )}
        </div>
    );
}
