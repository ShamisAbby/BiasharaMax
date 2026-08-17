import { ShoppingCartIcon } from '@heroicons/react/24/outline';
import { Head, Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export interface StorefrontBusiness {
    name: string;
    slug: string;
    logo_path: string | null;
    email: string;
    phone: string | null;
    theme_colors: {
        primary: string;
        secondary: string;
        accent: string;
        background: string;
        surface: string;
        text: string;
        muted: string;
    } | null;
    typography: { heading_font: string; body_font: string } | null;
}

function bunnyFontUrl(fonts: string[]): string {
    const families = fonts
        .filter(Boolean)
        .map((font) => font.trim().toLowerCase().replace(/\s+/g, '-'))
        .filter((value, index, all) => all.indexOf(value) === index)
        .map((family) => `${family}:400,500,600,700`)
        .join('|');

    return `https://fonts.bunny.net/css?family=${families}&display=swap`;
}

export default function StorefrontLayout({
    business,
    title,
    children,
}: PropsWithChildren<{ business: StorefrontBusiness; title: string }>) {
    const colors = business.theme_colors ?? {
        primary: '#4F46E5',
        secondary: '#312E81',
        accent: '#F59E0B',
        background: '#FFFFFF',
        surface: '#F8FAFC',
        text: '#0F172A',
        muted: '#64748B',
    };
    const fonts = business.typography ?? {
        heading_font: 'Inter',
        body_font: 'Inter',
    };

    const cssVars = {
        '--brand-primary': colors.primary,
        '--brand-secondary': colors.secondary,
        '--brand-accent': colors.accent,
        '--brand-background': colors.background,
        '--brand-surface': colors.surface,
        '--brand-text': colors.text,
        '--brand-muted': colors.muted,
        '--font-heading': `'${fonts.heading_font}', serif`,
        '--font-body': `'${fonts.body_font}', sans-serif`,
    } as React.CSSProperties;

    return (
        <div
            style={cssVars}
            className="min-h-screen bg-[var(--brand-background)] font-[var(--font-body)] text-[var(--brand-text)] antialiased"
        >
            <Head title={`${title} — ${business.name}`}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href={bunnyFontUrl([fonts.heading_font, fonts.body_font])}
                    rel="stylesheet"
                />
            </Head>

            <header className="bg-[var(--brand-background)]/90 sticky top-0 z-30 border-b border-black/5 backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <Link
                        href={route('public.website.show', business.slug)}
                        className="flex items-center gap-2.5"
                    >
                        {business.logo_path ? (
                            <img
                                src={business.logo_path}
                                alt={business.name}
                                className="h-9 w-9 rounded-full object-cover"
                            />
                        ) : (
                            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--brand-primary)] text-sm font-bold text-white">
                                {business.name.charAt(0).toUpperCase()}
                            </span>
                        )}
                        <span className="text-lg font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                            {business.name}
                        </span>
                    </Link>

                    <nav className="hidden items-center gap-7 md:flex">
                        <Link
                            href={route(
                                'public.website.products.index',
                                business.slug,
                            )}
                            className="text-[var(--brand-text)]/80 text-sm font-medium transition hover:text-[var(--brand-primary)]"
                        >
                            Shop
                        </Link>
                        <Link
                            href={route(
                                'public.website.blog.index',
                                business.slug,
                            )}
                            className="text-[var(--brand-text)]/80 text-sm font-medium transition hover:text-[var(--brand-primary)]"
                        >
                            Blog
                        </Link>
                    </nav>

                    <Link
                        href={route('public.website.cart.show', business.slug)}
                        className="inline-flex items-center gap-2 rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                    >
                        <ShoppingCartIcon className="h-4 w-4" />
                        Cart
                    </Link>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-6 py-10">{children}</main>

            <footer className="border-t border-black/5 bg-[var(--brand-surface)] px-6 py-10">
                <div className="mx-auto flex max-w-6xl flex-col items-center gap-3 text-center">
                    <span className="text-base font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                        {business.name}
                    </span>
                    <p className="text-[var(--brand-muted)]/70 text-xs">
                        Powered by BiasharaMax
                    </p>
                </div>
            </footer>
        </div>
    );
}
