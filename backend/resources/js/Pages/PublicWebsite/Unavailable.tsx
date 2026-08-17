import { Head } from '@inertiajs/react';

interface UnavailableBusiness {
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
}

/**
 * Shown when an owner has taken their website offline.
 *
 * A bare 404 was actively misleading here — it reads as "this business
 * doesn't exist", when the business is trading perfectly well and has
 * merely withdrawn its site. Anyone arriving from a saved link, a card or
 * a search result would reasonably conclude the business had closed.
 *
 * So this says what is actually true, and keeps whatever contact details
 * the business has on file, because a visitor who came looking for them
 * still needs a way to get in touch.
 */
export default function Unavailable({
    business,
}: {
    business: UnavailableBusiness;
}) {
    const contactRows = [
        business.phone && {
            label: 'Phone',
            value: business.phone,
            href: `tel:${business.phone}`,
        },
        business.email && {
            label: 'Email',
            value: business.email,
            href: `mailto:${business.email}`,
        },
        business.address && {
            label: 'Address',
            value: [business.address, business.city].filter(Boolean).join(', '),
            href: null,
        },
    ].filter(Boolean) as {
        label: string;
        value: string;
        href: string | null;
    }[];

    return (
        <>
            <Head>
                <title>{`${business.name} — website unavailable`}</title>
                {/*
                  Search engines should drop this from the index while it
                  is down, but keep following links so the site recovers
                  its ranking cleanly once it is published again.
                */}
                <meta name="robots" content="noindex, follow" />
            </Head>

            <div className="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-16">
                <div className="w-full max-w-md text-center">
                    <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-600 text-2xl font-bold text-white">
                        {business.name.charAt(0).toUpperCase()}
                    </span>

                    <h1 className="mt-6 text-2xl font-bold text-gray-900">
                        {business.name}
                    </h1>

                    <p className="mt-3 text-base leading-relaxed text-gray-600">
                        This website is temporarily unavailable. The business is
                        still operating — only the site is offline while it is
                        being updated.
                    </p>

                    {contactRows.length > 0 && (
                        <div className="mt-8 rounded-2xl border border-gray-200 bg-white p-6 text-left shadow-sm">
                            <h2 className="text-sm font-semibold text-gray-900">
                                Get in touch
                            </h2>
                            <dl className="mt-4 space-y-3">
                                {contactRows.map((row) => (
                                    <div
                                        key={row.label}
                                        className="flex gap-3 text-sm"
                                    >
                                        <dt className="w-20 shrink-0 text-gray-500">
                                            {row.label}
                                        </dt>
                                        <dd className="min-w-0 flex-1 break-words text-gray-900">
                                            {row.href ? (
                                                <a
                                                    href={row.href}
                                                    className="font-medium text-indigo-600 hover:underline"
                                                >
                                                    {row.value}
                                                </a>
                                            ) : (
                                                row.value
                                            )}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}

                    <p className="mt-8 text-xs text-gray-400">
                        Powered by BiasharaMax
                    </p>
                </div>
            </div>
        </>
    );
}
