import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
// Imported here rather than in app.css so it ships only with the public
// storefront bundle — the dashboard never loads it.
import { Bars3Icon, XMarkIcon } from '@heroicons/react/24/outline';
import '../../../css/public-website-luxe.css';
import {
    AboutSection,
    BookingSection,
    BusinessInfo,
    ContactSection,
    FaqSection,
    FeaturesSection,
    GallerySection,
    HeroSection,
    ItemsSection,
    TemplatePage,
    TestimonialsSection,
} from './Sections';

interface ThemeColors {
    primary: string;
    secondary: string;
    accent: string;
    background: string;
    surface: string;
    text: string;
    muted: string;
}

interface Typography {
    heading_font: string;
    body_font: string;
}

interface TemplateData {
    name: string;
    theme_colors: ThemeColors;
    typography: Typography;
    header_config: {
        style?: string;
        show_cta?: boolean;
        cta_label?: string;
    } | null;
    footer_config: { tagline?: string; show_social?: boolean } | null;
    seo_settings: { title_suffix?: string; meta_description?: string } | null;
    social_media: Record<string, string> | null;
    whatsapp_number: string | null;
    google_maps_embed: string | null;
    pages: TemplatePage[];
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

/**
 * The page types `renderSection` actually draws. Kept alongside it so the
 * two can't drift: anything not listed here falls through to `null`, and
 * linking to it would produce an anchor that scrolls nowhere.
 */
const RENDERABLE_PAGE_TYPES = new Set([
    'about',
    'products',
    'categories',
    'services',
    'gallery',
    'testimonials',
    'faq',
    'booking_form',
    'contact',
]);

function renderSection(page: TemplatePage, business: BusinessInfo) {
    switch (page.type) {
        case 'about':
            return <AboutSection key={page.slug} page={page} />;
        case 'products':
        case 'categories':
        case 'services':
            return <ItemsSection key={page.slug} page={page} />;
        case 'gallery':
            return <GallerySection key={page.slug} page={page} />;
        case 'testimonials':
            return <TestimonialsSection key={page.slug} page={page} />;
        case 'faq':
            return <FaqSection key={page.slug} page={page} />;
        case 'booking_form':
            return <BookingSection key={page.slug} page={page} />;
        case 'contact':
            return (
                <ContactSection
                    key={page.slug}
                    page={page}
                    business={business}
                />
            );
        default:
            return null;
    }
}

export default function PublicWebsiteShow({
    business,
    template,
    preview,
}: {
    business: BusinessInfo;
    template: TemplateData | null;
    /**
     * Present only when a platform admin is previewing a template from
     * the admin, absent on every real visitor request — so a customer
     * can never see the banner, and the check is the prop's existence
     * rather than a flag someone could set wrongly.
     */
    preview?: {
        templateName: string;
        status: string;
        backUrl: string;
    };
}) {
    const [menuOpen, setMenuOpen] = useState(false);

    const homepage = template?.pages.find((page) => page.type === 'homepage');
    const navPages = useMemo(
        () => template?.pages.filter((page) => page.type !== 'homepage') ?? [],
        [template],
    );

    /*
     * Where the hero's scroll cue points. Resolved here rather than inside
     * HeroSection because only this component knows what is rendered
     * beneath it: FeaturesSection returns null when the homepage has no
     * features, and renderSection ignores page types it has no case for.
     * A hardcoded anchor would silently become a dead link on any template
     * that omits that page — so if nothing renders below, the hero gets no
     * cue at all instead of a broken one.
     */
    const heroScrollTarget = useMemo(() => {
        const features = (homepage?.content?.features ?? []) as unknown[];

        if (Array.isArray(features) && features.length > 0) {
            return 'why-us';
        }

        return navPages.find((page) => RENDERABLE_PAGE_TYPES.has(page.type))
            ?.slug;
    }, [homepage, navPages]);

    if (!template) {
        return (
            <>
                <Head title={business.name} />
                <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-6 text-center">
                    <h1 className="text-xl font-semibold text-gray-900">
                        {business.name}
                    </h1>
                    <p className="mt-2 max-w-sm text-sm text-gray-500">
                        This business hasn&apos;t published a website yet.
                        Please check back soon.
                    </p>
                </div>
            </>
        );
    }

    const colors = template.theme_colors;
    const fonts = template.typography;
    const headerCta = template.header_config?.cta_label ?? 'Contact us';
    const footerTagline = template.footer_config?.tagline;

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

    // Opt-in premium presentation. Templates that don't ask for it render
    // exactly as before — every rule in public-website-luxe.css is scoped
    // under this attribute, so the shared section components below need
    // no branching and no other template can regress.
    const siteStyle =
        template.header_config?.style === 'luxe' ? 'luxe' : undefined;

    return (
        <div
            style={cssVars}
            data-site-style={siteStyle}
            className="bg-[var(--brand-background)] font-[var(--font-body)] text-[var(--brand-text)] antialiased"
        >
            {/*
              Only rendered for an admin previewing from the platform.

              Sticky rather than inline: the whole point of a preview is
              to scroll through the page, and a banner that scrolls away
              leaves an admin looking at what appears to be a live
              customer site with no way back. The sample content below is
              invented, and saying so matters — otherwise "hello@example.com"
              reads as a data problem rather than a placeholder.
            */}
            {preview && (
                <div className="sticky top-0 z-50 flex flex-wrap items-center justify-between gap-3 bg-gray-900 px-4 py-2.5 text-sm text-white">
                    <p className="flex flex-wrap items-center gap-2">
                        <span className="rounded bg-white/15 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide">
                            Preview
                        </span>
                        <span className="font-medium">
                            {preview.templateName}
                        </span>
                        <span className="text-white/60">
                            · {preview.status} · sample content, not a real
                            business
                        </span>
                    </p>

                    <a
                        href={preview.backUrl}
                        className="shrink-0 rounded-md bg-white/15 px-3 py-1 text-xs font-medium transition hover:bg-white/25"
                    >
                        Back to templates
                    </a>
                </div>
            )}
            <Head>
                <title>
                    {`${business.name} ${template.seo_settings?.title_suffix ?? ''}`.trim()}
                </title>
                {template.seo_settings?.meta_description && (
                    <meta
                        name="description"
                        content={template.seo_settings.meta_description}
                    />
                )}
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href={bunnyFontUrl([fonts.heading_font, fonts.body_font])}
                    rel="stylesheet"
                />
            </Head>

            <header className="bg-[var(--brand-background)]/90 sticky top-0 z-30 border-b border-black/5 backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <a href="#home" className="flex items-center gap-2.5">
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
                    </a>

                    <nav className="hidden items-center gap-7 md:flex">
                        {navPages.map((page) => (
                            <a
                                key={page.slug}
                                href={`#${page.slug}`}
                                className="text-[var(--brand-text)]/80 text-sm font-medium transition hover:text-[var(--brand-primary)]"
                            >
                                {page.title}
                            </a>
                        ))}
                        {business.has_shop && (
                            <Link
                                href={route(
                                    'public.website.products.index',
                                    business.slug,
                                )}
                                className="text-[var(--brand-text)]/80 text-sm font-medium transition hover:text-[var(--brand-primary)]"
                            >
                                Shop
                            </Link>
                        )}
                        {business.has_blog && (
                            <Link
                                href={route(
                                    'public.website.blog.index',
                                    business.slug,
                                )}
                                className="text-[var(--brand-text)]/80 text-sm font-medium transition hover:text-[var(--brand-primary)]"
                            >
                                Blog
                            </Link>
                        )}
                    </nav>

                    <a
                        href="#contact"
                        className="hidden rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 md:inline-block"
                    >
                        {headerCta}
                    </a>

                    <button
                        type="button"
                        onClick={() => setMenuOpen((open) => !open)}
                        className="rounded-md p-2 text-[var(--brand-text)] md:hidden"
                        aria-label="Toggle menu"
                    >
                        {menuOpen ? (
                            <XMarkIcon className="h-6 w-6" />
                        ) : (
                            <Bars3Icon className="h-6 w-6" />
                        )}
                    </button>
                </div>

                {menuOpen && (
                    <nav className="space-y-1 border-t border-black/5 px-6 py-4 md:hidden">
                        {navPages.map((page) => (
                            <a
                                key={page.slug}
                                href={`#${page.slug}`}
                                onClick={() => setMenuOpen(false)}
                                className="text-[var(--brand-text)]/80 block py-2 text-sm font-medium"
                            >
                                {page.title}
                            </a>
                        ))}
                        {business.has_shop && (
                            <Link
                                href={route(
                                    'public.website.products.index',
                                    business.slug,
                                )}
                                onClick={() => setMenuOpen(false)}
                                className="text-[var(--brand-text)]/80 block py-2 text-sm font-medium"
                            >
                                Shop
                            </Link>
                        )}
                        {business.has_blog && (
                            <Link
                                href={route(
                                    'public.website.blog.index',
                                    business.slug,
                                )}
                                onClick={() => setMenuOpen(false)}
                                className="text-[var(--brand-text)]/80 block py-2 text-sm font-medium"
                            >
                                Blog
                            </Link>
                        )}
                    </nav>
                )}
            </header>

            <main>
                {homepage && (
                    <>
                        <HeroSection
                            page={homepage}
                            ctaLabel={headerCta}
                            scrollToId={heroScrollTarget}
                        />
                        <FeaturesSection page={homepage} />
                    </>
                )}

                {navPages.map((page) => renderSection(page, business))}
            </main>

            <footer className="border-t border-black/5 bg-[var(--brand-surface)] px-6 py-10">
                <div className="mx-auto flex max-w-6xl flex-col items-center gap-3 text-center">
                    <span className="text-base font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                        {business.name}
                    </span>
                    {footerTagline && (
                        <p className="text-sm text-[var(--brand-muted)]">
                            {footerTagline}
                        </p>
                    )}
                    <p className="text-xs text-[var(--brand-muted)]">
                        &copy; {new Date().getFullYear()} {business.name}. All
                        rights reserved.
                    </p>
                    <p className="text-[var(--brand-muted)]/70 text-xs">
                        Powered by BiasharaMax
                    </p>
                </div>
            </footer>
        </div>
    );
}
