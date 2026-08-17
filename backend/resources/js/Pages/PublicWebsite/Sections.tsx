import {
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    EnvelopeIcon,
    MapPinIcon,
    PhoneIcon,
    PhotoIcon,
} from '@heroicons/react/24/outline';
import { ReactNode } from 'react';

export interface TemplatePage {
    type: string;
    title: string;
    slug: string;
    content: Record<string, unknown> | null;
}

export interface BusinessInfo {
    name: string;
    slug: string;
    logo_path: string | null;
    email: string;
    phone: string | null;
    address: string | null;
    city: string | null;
    has_shop: boolean;
    has_blog: boolean;
}

function Section({
    id,
    tone,
    children,
}: {
    id: string;
    tone?: 'surface' | 'plain';
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            className={`px-6 py-16 sm:py-20 ${tone === 'surface' ? 'bg-[var(--brand-surface)]' : ''}`}
        >
            <div className="mx-auto max-w-6xl">{children}</div>
        </section>
    );
}

function Eyebrow({ children }: { children: ReactNode }) {
    return (
        <p className="text-sm font-semibold uppercase tracking-wide text-[var(--brand-primary)]">
            {children}
        </p>
    );
}

export function HeroSection({
    page,
    ctaLabel,
    scrollToId,
}: {
    page: TemplatePage;
    ctaLabel: string;
    /**
     * Anchor for the scroll cue. Supplied by the caller because only it
     * knows which section it actually renders next — a hardcoded
     * "#about" would be a dead link on a template without an About page.
     * Omitted means no cue.
     */
    scrollToId?: string;
}) {
    const hero = (page.content?.hero ?? {}) as Record<string, string>;

    return (
        <section
            id={page.slug}
            /*
             * `min-h` rather than `h`: the hero fills the screen on a
             * normal viewport, but a long headline or a short landscape
             * phone can still push it taller instead of clipping the CTAs.
             *
             * 100svh, not 100vh — on mobile browsers `vh` is measured
             * against the viewport WITHOUT the address bar, so a 100vh
             * hero pushes its own buttons under the browser chrome until
             * the user scrolls. `svh` uses the smallest (visible) height,
             * which is what "fits the screen" actually means on a phone.
             * The offset is the sticky header above it: py-4 (2rem) around
             * an h-9 logo (2.25rem) plus its 1px bottom border.
             */
            className="relative flex min-h-[calc(100svh-4.25rem-1px)] items-center overflow-hidden bg-gradient-to-br from-[var(--brand-primary)] to-[var(--brand-secondary)] px-6 py-20 text-white"
        >
            {/* Ambient light. Sized in vmax so the composition holds at
                any aspect ratio rather than only on a laptop. */}
            <div className="pointer-events-none absolute -right-[10vmax] -top-[10vmax] h-[45vmax] w-[45vmax] rounded-full bg-white/10 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-[15vmax] -left-[8vmax] h-[50vmax] w-[50vmax] rounded-full bg-white/[0.07] blur-3xl" />

            {/* A soft floor tying the hero into the section beneath it, so
                the colour change reads as a transition rather than a seam. */}
            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-32 bg-gradient-to-b from-transparent to-black/10" />

            <div className="relative mx-auto w-full max-w-3xl text-center">
                {hero.eyebrow && (
                    <p className="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.15em] text-white/90 backdrop-blur-sm">
                        {hero.eyebrow}
                    </p>
                )}
                {/*
                 * clamp() rather than fixed breakpoint sizes: the headline
                 * scales continuously with the viewport, so it stays
                 * proportionate on a phone and on a 27" monitor without
                 * needing a size for every breakpoint. `text-balance`
                 * stops the last line from orphaning a single word.
                 */}
                <h1 className="mt-6 text-balance text-[clamp(2.25rem,6vw,4.5rem)] font-[var(--font-heading)] font-bold leading-[1.05] tracking-tight">
                    {hero.headline}
                </h1>
                <p className="mx-auto mt-6 max-w-xl text-pretty text-lg leading-relaxed text-white/80">
                    {hero.subheadline}
                </p>
                <div className="mt-10 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                    <a
                        href="#contact"
                        className="rounded-xl bg-white px-7 py-3.5 text-sm font-semibold text-[var(--brand-primary)] shadow-xl shadow-black/10 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl"
                    >
                        {ctaLabel || hero.primary_cta || 'Get in touch'}
                    </a>
                    {/* Only offered when there is somewhere real to send
                        them. This previously pointed at "#about" on the
                        homepage, which scrolled nowhere on any template
                        without an About page. */}
                    {hero.secondary_cta && scrollToId && (
                        <a
                            href={`#${scrollToId}`}
                            className="rounded-xl border border-white/30 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur-sm transition duration-200 hover:-translate-y-0.5 hover:border-white/60 hover:bg-white/10"
                        >
                            {hero.secondary_cta}
                        </a>
                    )}
                </div>
            </div>

            {/* Scroll cue — the cost of a full-height hero is that nothing
                below it is visible, so it needs to signal there is more.
                Hidden on short viewports where it would crowd the CTAs. */}
            {scrollToId && (
                <a
                    href={`#${scrollToId}`}
                    aria-label="Scroll to content"
                    className="absolute inset-x-0 bottom-8 mx-auto hidden h-9 w-9 items-center justify-center rounded-full border border-white/25 text-white/70 transition hover:border-white/60 hover:text-white sm:flex"
                >
                    <svg
                        className="h-4 w-4 animate-bounce"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth={2}
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M19.5 8.25 12 15.75 4.5 8.25"
                        />
                    </svg>
                </a>
            )}
        </section>
    );
}

export function FeaturesSection({ page }: { page: TemplatePage }) {
    const features = (page.content?.features ?? []) as {
        title: string;
        description: string;
    }[];

    if (features.length === 0) return null;

    return (
        <Section id="why-us" tone="surface">
            <div className="grid gap-8 sm:grid-cols-3">
                {features.map((feature) => (
                    <div
                        key={feature.title}
                        className="flex flex-col items-start gap-3"
                    >
                        <CheckCircleIcon className="h-7 w-7 text-[var(--brand-primary)]" />
                        <h3 className="text-lg font-[var(--font-heading)] font-semibold text-[var(--brand-text)]">
                            {feature.title}
                        </h3>
                        <p className="text-sm leading-relaxed text-[var(--brand-muted)]">
                            {feature.description}
                        </p>
                    </div>
                ))}
            </div>
        </Section>
    );
}

export function AboutSection({ page }: { page: TemplatePage }) {
    const heading = (page.content?.heading ?? page.title) as string;
    const body = (page.content?.body ?? '') as string;

    return (
        <Section id={page.slug}>
            <div className="mx-auto max-w-2xl text-center">
                <Eyebrow>About</Eyebrow>
                <h2 className="mt-2 text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                    {heading}
                </h2>
                <p className="mt-4 text-base leading-relaxed text-[var(--brand-muted)]">
                    {body}
                </p>
            </div>
        </Section>
    );
}

export function ItemsSection({ page }: { page: TemplatePage }) {
    const heading = (page.content?.heading ?? page.title) as string;
    const intro = (page.content?.intro ?? '') as string;
    const items = (page.content?.items ?? []) as {
        name: string;
        description: string;
    }[];

    return (
        <Section id={page.slug} tone="surface">
            <div className="mx-auto mb-10 max-w-2xl text-center">
                <Eyebrow>{page.title}</Eyebrow>
                <h2 className="mt-2 text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                    {heading}
                </h2>
                {intro && (
                    <p className="mt-3 text-base leading-relaxed text-[var(--brand-muted)]">
                        {intro}
                    </p>
                )}
            </div>

            <div className="grid gap-6 sm:grid-cols-3">
                {items.map((item) => (
                    <div
                        key={item.name}
                        className="rounded-2xl bg-[var(--brand-background)] p-6 shadow-sm ring-1 ring-black/5"
                    >
                        <div className="mb-3 h-1.5 w-10 rounded-full bg-[var(--brand-accent)]" />
                        <h3 className="text-base font-[var(--font-heading)] font-semibold text-[var(--brand-text)]">
                            {item.name}
                        </h3>
                        <p className="mt-1.5 text-sm leading-relaxed text-[var(--brand-muted)]">
                            {item.description}
                        </p>
                    </div>
                ))}
            </div>
        </Section>
    );
}

export function GallerySection({ page }: { page: TemplatePage }) {
    const heading = (page.content?.heading ?? page.title) as string;
    const intro = (page.content?.intro ?? '') as string;

    return (
        <Section id={page.slug}>
            <div className="mx-auto mb-10 max-w-2xl text-center">
                <Eyebrow>Gallery</Eyebrow>
                <h2 className="mt-2 text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                    {heading}
                </h2>
                {intro && (
                    <p className="mt-3 text-sm text-[var(--brand-muted)]">
                        {intro}
                    </p>
                )}
            </div>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                {Array.from({ length: 6 }).map((_, index) => (
                    <div
                        key={index}
                        className="flex aspect-square items-center justify-center rounded-xl bg-[var(--brand-surface)] ring-1 ring-black/5"
                    >
                        <PhotoIcon className="h-8 w-8 text-[var(--brand-muted)]" />
                    </div>
                ))}
            </div>
        </Section>
    );
}

export function TestimonialsSection({ page }: { page: TemplatePage }) {
    const heading = (page.content?.heading ?? page.title) as string;
    const items = (page.content?.items ?? []) as {
        quote: string;
        author: string;
    }[];

    return (
        <Section id={page.slug} tone="surface">
            <div className="mx-auto mb-10 max-w-2xl text-center">
                <Eyebrow>Testimonials</Eyebrow>
                <h2 className="mt-2 text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                    {heading}
                </h2>
            </div>
            <div className="grid gap-6 sm:grid-cols-3">
                {items.map((item, index) => (
                    <figure
                        key={index}
                        className="rounded-2xl bg-[var(--brand-background)] p-6 shadow-sm ring-1 ring-black/5"
                    >
                        <blockquote className="text-sm leading-relaxed text-[var(--brand-text)]">
                            &ldquo;{item.quote}&rdquo;
                        </blockquote>
                        <figcaption className="mt-4 text-xs font-semibold uppercase tracking-wide text-[var(--brand-muted)]">
                            {item.author}
                        </figcaption>
                    </figure>
                ))}
            </div>
        </Section>
    );
}

export function FaqSection({ page }: { page: TemplatePage }) {
    const heading = (page.content?.heading ?? page.title) as string;
    const items = (page.content?.items ?? []) as {
        question: string;
        answer: string;
    }[];

    return (
        <Section id={page.slug}>
            <div className="mx-auto mb-10 max-w-2xl text-center">
                <Eyebrow>FAQ</Eyebrow>
                <h2 className="mt-2 text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                    {heading}
                </h2>
            </div>
            <div className="mx-auto max-w-2xl divide-y divide-black/5">
                {items.map((item, index) => (
                    <details key={index} className="group py-4">
                        <summary className="cursor-pointer list-none text-sm font-semibold text-[var(--brand-text)]">
                            {item.question}
                        </summary>
                        <p className="mt-2 text-sm leading-relaxed text-[var(--brand-muted)]">
                            {item.answer}
                        </p>
                    </details>
                ))}
            </div>
        </Section>
    );
}

export function BookingSection({ page }: { page: TemplatePage }) {
    const heading = (page.content?.heading ?? page.title) as string;
    const intro = (page.content?.intro ?? '') as string;

    return (
        <Section id={page.slug} tone="surface">
            <div className="mx-auto max-w-xl text-center">
                <Eyebrow>Booking</Eyebrow>
                <h2 className="mt-2 text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                    {heading}
                </h2>
                {intro && (
                    <p className="mt-3 text-sm text-[var(--brand-muted)]">
                        {intro}
                    </p>
                )}
                <a
                    href="#contact"
                    className="mt-6 inline-block rounded-lg bg-[var(--brand-primary)] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                >
                    Book now
                </a>
            </div>
        </Section>
    );
}

export function ContactSection({
    page,
    business,
}: {
    page: TemplatePage;
    business: BusinessInfo;
}) {
    const heading = (page.content?.heading ?? page.title) as string;
    const intro = (page.content?.intro ?? '') as string;
    const whatsappNumber = business.phone?.replace(/[^0-9]/g, '');

    return (
        <Section id={page.slug}>
            <div className="grid gap-10 sm:grid-cols-2">
                <div>
                    <Eyebrow>Contact</Eyebrow>
                    <h2 className="mt-2 text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                        {heading}
                    </h2>
                    {intro && (
                        <p className="mt-3 text-sm leading-relaxed text-[var(--brand-muted)]">
                            {intro}
                        </p>
                    )}

                    <ul className="mt-6 space-y-3 text-sm text-[var(--brand-text)]">
                        {business.address && (
                            <li className="flex items-start gap-3">
                                <MapPinIcon className="mt-0.5 h-5 w-5 shrink-0 text-[var(--brand-primary)]" />
                                <span>
                                    {business.address}
                                    {business.city ? `, ${business.city}` : ''}
                                </span>
                            </li>
                        )}
                        {business.phone && (
                            <li className="flex items-center gap-3">
                                <PhoneIcon className="h-5 w-5 shrink-0 text-[var(--brand-primary)]" />
                                <span>{business.phone}</span>
                            </li>
                        )}
                        <li className="flex items-center gap-3">
                            <EnvelopeIcon className="h-5 w-5 shrink-0 text-[var(--brand-primary)]" />
                            <span>{business.email}</span>
                        </li>
                    </ul>

                    {whatsappNumber && (
                        <a
                            href={`https://wa.me/${whatsappNumber}`}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-6 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            <ChatBubbleLeftRightIcon className="h-5 w-5" />
                            Chat on WhatsApp
                        </a>
                    )}
                </div>

                <div className="flex items-center justify-center rounded-2xl bg-[var(--brand-surface)] p-6 ring-1 ring-black/5">
                    <p className="text-center text-sm text-[var(--brand-muted)]">
                        Visit us in person or reach out using the details to
                        start a conversation — we&apos;d love to help.
                    </p>
                </div>
            </div>
        </Section>
    );
}
