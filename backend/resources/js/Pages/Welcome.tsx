import ApplicationLogo from '@/Components/ApplicationLogo';
import FadeInSection, { fadeUpItem } from '@/Components/FadeInSection';
import PrimaryButton from '@/Components/PrimaryButton';
import SectionHeading from '@/Components/SectionHeading';
import WhatsAppButton from '@/Components/WhatsAppButton';
import { formatCurrency } from '@/lib/currency';
import { PageProps, SubscriptionPlan } from '@/types';
import {
    ArrowDownTrayIcon,
    ArrowRightIcon,
    BanknotesIcon,
    Bars3Icon,
    BeakerIcon,
    BellAlertIcon,
    BuildingStorefrontIcon,
    CakeIcon,
    CalculatorIcon,
    ChatBubbleLeftRightIcon,
    CheckIcon,
    ChevronDownIcon,
    ClockIcon,
    CommandLineIcon,
    ComputerDesktopIcon,
    CpuChipIcon,
    CubeIcon,
    EllipsisHorizontalIcon,
    EnvelopeIcon,
    GlobeAltIcon,
    MapPinIcon,
    PhoneIcon,
    ScissorsIcon,
    ShieldCheckIcon,
    ShoppingCartIcon,
    SignalSlashIcon,
    SparklesIcon,
    Squares2X2Icon,
    TruckIcon,
    UserGroupIcon,
    WrenchIcon,
    WrenchScrewdriverIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { Head, Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ReactNode, useEffect, useMemo, useState } from 'react';

/**
 * One type scale for the whole landing page.
 *
 * Before this, the same role was set at four different sizes: card titles
 * ran text-sm in Features, text-base in "How it works" and text-lg in
 * Download and Pricing, and body copy alternated between text-xs and
 * text-sm with no rule behind which. Scroll-snapping makes that worse
 * than it sounds — each section arrives alone and full-screen, so the
 * reader meets each size in isolation and the page reads as several
 * pages that happen to share a colour.
 *
 * Roles, not sizes. Anything added later picks the role it *is* rather
 * than eyeballing a number against its neighbours, which is how the drift
 * happened in the first place.
 *
 * The sizes were raised a step after seeing the page on a wide monitor:
 * 14px body across a 2000px-wide three-column grid is a long line of small
 * text, and the reader is a shopkeeper deciding whether to trust this with
 * their takings, not a developer scanning a dense dashboard. Body sits at
 * 16px, which is the browser default and the size everything else on the
 * web has taught people to expect.
 *
 * Only `sectionTitle` and `sectionLede` grow again at `lg`. Scaling every
 * role at the top breakpoint would make a laptop's cards look inflated to
 * fix a problem that only exists on a large display.
 */
const TYPE = {
    /** Every section's h2. The hero's h1 is deliberately not in here. */
    sectionTitle: 'text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl',
    /** The one-line explanation under a section title. */
    sectionLede: 'text-lg leading-relaxed lg:text-xl',
    /** Titles inside cards and tiles — features, steps, platforms, plans. */
    cardTitle: 'text-lg font-semibold',
    /** Body copy inside those cards. */
    cardBody: 'text-base leading-relaxed',
    /** Supporting detail: requirements, footnotes, captions. */
    meta: 'text-sm',
    /** Small uppercase labels above a title or over a column. */
    eyebrow: 'text-sm font-semibold uppercase tracking-wider',

    /**
     * The page title. One per page, and the only thing on it allowed to be
     * larger than a section title.
     */
    heroTitle: 'text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl',
    /** The paragraph under it — one step up from a section lede. */
    heroLede: 'text-lg leading-relaxed sm:text-xl',

    /** Every button and call to action, in the nav and in the sections. */
    button: 'text-base font-semibold',
    /** Header and footer navigation. */
    navLink: 'text-base font-medium',
    /** The BiasharaMax wordmark. */
    brand: 'text-lg font-bold',
    /**
     * Pills like "Most popular". Smaller than body on purpose: a badge
     * labels something else, and at body size it competes with the thing
     * it is labelling.
     */
    badge: 'text-xs font-semibold',
    /** The number on a pricing card, which has to dominate its card. */
    price: 'text-3xl font-bold sm:text-4xl',
} as const;

const NAV_LINKS = [
    { href: '#features', label: 'Features' },
    { href: '#businesses', label: 'Who it’s for' },
    { href: '#download', label: 'Download' },
    { href: '#pricing', label: 'Pricing' },
];

interface DesktopPlatform {
    key: 'windows' | 'macos' | 'linux';
    name: string;
    requirement: string;
    format: string;
    url: string | null;
    size: string | null;
    checksum: string | null;
}

/**
 * Deliberately Heroicons rather than the Windows, Apple and Tux marks.
 *
 * Three logos drawn in three different houses' styles, at three different
 * optical weights, sit badly next to each other and next to the rest of
 * this page. One consistent set reads as designed; mixed brand marks read
 * as pasted in. The OS name is set large directly underneath, so nothing
 * is lost in recognition.
 *
 * Swapping in real brand SVGs later is a one-line change per entry.
 */
const PLATFORM_ICONS = {
    windows: Squares2X2Icon,
    macos: ComputerDesktopIcon,
    linux: CommandLineIcon,
} as const;

interface DesktopApp {
    version: string | null;
    releasedAt: string | null;
    releaseNotesUrl: string | null;
    platforms: DesktopPlatform[];
    isAvailable: boolean;
}

/**
 * Detects which download to put forward, so the visitor doesn't have to
 * work out which of three buttons is theirs.
 *
 * Returns `null` until the effect runs. That matters: this component is
 * server-rendered, and guessing an OS during SSR would ship markup that
 * highlights the wrong card and then visibly re-arranges on hydration.
 * Neutral until known is the correct default — a wrong highlight is worse
 * than no highlight, because people trust the highlight.
 */
function useDetectedPlatform(): DesktopPlatform['key'] | null {
    const [platform, setPlatform] = useState<DesktopPlatform['key'] | null>(
        null,
    );

    useEffect(() => {
        // `userAgentData.platform` is the non-deprecated source where it
        // exists (Chromium); `userAgent` is the fallback everywhere else.
        const hinted = (
            navigator as Navigator & { userAgentData?: { platform?: string } }
        ).userAgentData?.platform;

        const haystack = `${hinted ?? ''} ${navigator.userAgent}`.toLowerCase();

        // Phones and tablets first, and they resolve to nothing.
        //
        // Not a detail: an Android user agent contains the word "Linux",
        // and an iPad's contains "Macintosh". Checking desktop platforms
        // first would confidently offer a `.AppImage` to someone holding a
        // phone. Nobody on these can run the till, so no card is right.
        if (/android|iphone|ipad|ipod/.test(haystack)) {
            return;
        }

        if (haystack.includes('mac')) {
            setPlatform('macos');
        } else if (haystack.includes('win')) {
            setPlatform('windows');
        } else if (haystack.includes('linux') || haystack.includes('x11')) {
            setPlatform('linux');
        }
    }, []);

    return platform;
}

const FEATURES = [
    {
        icon: CubeIcon,
        title: 'Inventory',
        description:
            'Track stock across branches and warehouses, batches, expiry dates and barcodes in real time.',
    },
    {
        icon: ShoppingCartIcon,
        title: 'POS & Sales',
        description:
            'Fast, touch-friendly point of sale with multiple payment methods, discounts and receipts.',
    },
    {
        icon: TruckIcon,
        title: 'Purchasing',
        description:
            'Manage suppliers and purchase orders, and keep stock levels accurate as deliveries arrive.',
    },
    {
        icon: UserGroupIcon,
        title: 'CRM & Debt',
        description:
            'Customer profiles, loyalty, and debt tracking so nothing owed to your business gets forgotten.',
    },
    {
        icon: CalculatorIcon,
        title: 'Accounting',
        description:
            'Expenses, income, and cashbook in one place — know your numbers without a spreadsheet.',
    },
    {
        icon: GlobeAltIcon,
        title: 'Your own website',
        description:
            'Every business gets a professional website, edited from the dashboard. No coding required.',
    },
    {
        icon: BellAlertIcon,
        title: 'Notifications',
        description:
            'SMS, email, WhatsApp and push notifications keep you and your customers in the loop.',
    },
    {
        icon: SignalSlashIcon,
        title: 'Works offline',
        description:
            'Keep selling and tracking stock even when the internet drops. Syncs automatically when it’s back.',
    },
    {
        icon: ShieldCheckIcon,
        title: 'Roles & permissions',
        description:
            'Give every employee exactly the access they need — owner, manager, cashier, and more.',
    },
];

/** Public contact details, shaped by HomeController::contact(). */
interface LandingContact {
    whatsapp: string | null;
    whatsappUrl: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    hours: string | null;
}

interface LandingBusinessType {
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
}

/**
 * Maps the icon name stored against each business type to its component.
 *
 * `business_types.icon` has always held a real Heroicon name — `cake` for
 * restaurants, `beaker` for pharmacies, `scissors` for salons — set by
 * the seeder and editable in the platform admin. The landing page ignored
 * it and drew `BuildingStorefrontIcon` for all eleven, tinted a different
 * colour each time. Eleven identical shopfronts is not a distinction; it
 * is decoration pretending to be one, and a visitor scanning for their
 * own trade had nothing to scan.
 *
 * Unrecognised names fall back to the shopfront, so an admin typing an
 * icon this map has never heard of gets a plain tile rather than a hole.
 */
const BUSINESS_TYPE_ICONS: Record<string, typeof BuildingStorefrontIcon> = {
    'building-storefront': BuildingStorefrontIcon,
    'shopping-cart': ShoppingCartIcon,
    cake: CakeIcon,
    beaker: BeakerIcon,
    wrench: WrenchIcon,
    'cpu-chip': CpuChipIcon,
    sparkles: SparklesIcon,
    scissors: ScissorsIcon,
    truck: TruckIcon,
    'wrench-screwdriver': WrenchScrewdriverIcon,
    'ellipsis-horizontal': EllipsisHorizontalIcon,
};

const HERO_FLOATING_ICONS = [
    { icon: CubeIcon, className: 'left-[8%] top-[24%]', duration: 7, delay: 0 },
    {
        icon: ShoppingCartIcon,
        className: 'right-[12%] top-[20%]',
        duration: 8,
        delay: 1,
    },
    {
        icon: BanknotesIcon,
        className: 'left-[16%] bottom-[26%]',
        duration: 9,
        delay: 2,
    },
    {
        icon: TruckIcon,
        className: 'right-[18%] bottom-[22%]',
        duration: 6.5,
        delay: 0.5,
    },
    {
        icon: CalculatorIcon,
        className: 'left-[46%] top-[14%]',
        duration: 7.5,
        delay: 1.5,
    },
    {
        icon: GlobeAltIcon,
        className: 'right-[44%] bottom-[16%]',
        duration: 8.5,
        delay: 2.5,
    },
];

/**
 * How many plan features a pricing card lists before it summarises the
 * rest. Six is what fits alongside the price and the button at the
 * section's fixed height without the card growing taller than its
 * neighbours.
 */
const PLAN_FEATURE_LIMIT = 6;

/**
 * Note step one takes the trial length rather than stating it.
 *
 * "30-day free trial" was written into the copy while the actual length
 * comes from `subscription_plans.trial_days`, which an operator can
 * change. A landing page promising thirty days while sign-up hands over
 * fourteen is a promise the product then breaks on the first screen.
 */
const HOW_IT_WORKS = [
    {
        step: '1',
        icon: BuildingStorefrontIcon,
        title: 'Create your business',
        description: (trialDays: number | null) =>
            trialDays === null
                ? 'Register in minutes and start a free trial — no card required.'
                : `Register in minutes and start a ${trialDays}-day free trial — no card required.`,
        detail: 'Name, business type, currency. That is the whole form.',
    },
    {
        step: '2',
        icon: UserGroupIcon,
        title: 'Set up your team',
        description: () =>
            'Add branches, invite employees and assign roles with exactly the access they need.',
        detail: 'Owner, manager, cashier — or roles you define yourself.',
    },
    {
        step: '3',
        icon: ShoppingCartIcon,
        title: 'Run your business',
        description: () =>
            'Sell, track stock, manage customers and get paid — online or offline.',
        detail: 'On the web, or on a till that keeps working without internet.',
    },
];

/**
 * One screen, one section — from `sm` upwards.
 *
 * Full-viewport panels are a desktop treatment and a bad phone one. Nine
 * feature cards in a single column cannot be made to fit 100vh at a legible
 * size, and phones already opt out of snapping (see `.snap-page`), so a
 * fixed height there would only overflow into the section below. Below `sm`
 * these are ordinary sections with ordinary padding.
 *
 * `min-h-[600px]` is the escape hatch that stops the fixed height being a
 * trap. On a short window — a laptop with a browser toolbar and a dock, or
 * anyone at 150% zoom — a strict `h-screen` would clip content with no way
 * to reach it, because the section cannot scroll and the page snaps past
 * it. Below 600px the section grows instead, and the stylesheet drops
 * snapping at exactly the same breakpoint so the two stay in agreement.
 *
 * `pt-[73px]` is the header's height. Sections snap to their own top, which
 * sits underneath the sticky header, so the centring box has to start below
 * it or content drifts up behind the nav.
 *
 * Note the absence of `scroll-mt-*`, which the anchor targets used to
 * carry: `scroll-margin` also offsets the snap position, so leaving it on
 * would park every section 5rem down from where it should rest.
 */
function SnapSection({
    id,
    className = '',
    children,
}: {
    id?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            className={`flex w-full snap-start snap-always items-center py-16 sm:h-screen sm:min-h-[600px] sm:py-12 sm:pt-[73px] ${className}`}
        >
            <div className="mx-auto w-full max-w-7xl px-6">{children}</div>
        </section>
    );
}

/**
 * The screenshot-that-isn't on the landing page.
 *
 * The previous version was four stat cards above a plain gradient
 * rectangle. That rectangle was the largest thing in the frame and showed
 * nothing at all — on the one section whose entire job is "here is what
 * you are buying", the biggest element read as a component that had
 * failed to load. It also had no sidebar, so it did not resemble the
 * actual dashboard a vendor lands on.
 *
 * Everything here is fixed, invented data. That is a deliberate choice
 * over wiring it to real figures: this is a public page seen by people
 * with no account, and the numbers exist to show the *shape* of the
 * screen. Nothing here should ever be mistaken for a live reading.
 */
const PREVIEW_STATS = [
    {
        label: "Today's sales",
        value: 'TZS 1,940,200',
        delta: '+12% vs yesterday',
        deltaTone: 'text-emerald-600 dark:text-emerald-400',
        valueTone: 'text-gray-900 dark:text-gray-100',
    },
    {
        label: 'Outstanding debts',
        value: 'TZS 286,000',
        delta: '4 customers',
        deltaTone: 'text-gray-500 dark:text-gray-400',
        valueTone: 'text-gray-900 dark:text-gray-100',
    },
    {
        label: 'Stock value',
        value: 'TZS 28.5M',
        delta: '1,204 items',
        deltaTone: 'text-gray-500 dark:text-gray-400',
        valueTone: 'text-gray-900 dark:text-gray-100',
    },
    {
        label: 'Needs reordering',
        value: '7',
        delta: 'Below minimum',
        deltaTone: 'text-rose-600 dark:text-rose-400',
        valueTone: 'text-rose-600 dark:text-rose-400',
    },
] as const;

/** Heights as percentages so the chart needs no measuring or library. */
const PREVIEW_WEEK = [
    { day: 'Mon', height: 46 },
    { day: 'Tue', height: 62 },
    { day: 'Wed', height: 38 },
    { day: 'Thu', height: 71 },
    { day: 'Fri', height: 88 },
    { day: 'Sat', height: 100 },
    { day: 'Sun', height: 54 },
] as const;

const PREVIEW_LOW_STOCK = [
    { name: 'Sugar 1kg', left: '3 left' },
    { name: 'Cooking oil 5L', left: '2 left' },
    { name: 'Maize flour 2kg', left: '5 left' },
] as const;

const PREVIEW_NAV = [
    { label: 'Dashboard', icon: Squares2X2Icon, active: true },
    { label: 'Point of sale', icon: ShoppingCartIcon, active: false },
    { label: 'Inventory', icon: CubeIcon, active: false },
    { label: 'Customers', icon: UserGroupIcon, active: false },
    { label: 'Finance', icon: BanknotesIcon, active: false },
] as const;

function DashboardPreview() {
    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
            {/* Browser chrome */}
            <div className="flex items-center gap-2 border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                <span className="h-3 w-3 rounded-full bg-red-400" />
                <span className="h-3 w-3 rounded-full bg-yellow-400" />
                <span className="h-3 w-3 rounded-full bg-green-400" />
                <span className="ms-3 truncate text-xs text-gray-400">
                    app.biasharamax.com/dashboard
                </span>
            </div>

            <div className="flex">
                {/*
                  Hidden below `sm`, where it would take a third of the
                  width to show five words. The stat cards are the part
                  worth the space on a phone.
                */}
                <aside className="hidden w-44 shrink-0 border-e border-gray-100 bg-gray-50/60 p-3 dark:border-gray-700 dark:bg-gray-900/40 sm:block">
                    <div className="flex items-center gap-2 px-2 pb-4">
                        <span className="flex h-6 w-6 items-center justify-center rounded bg-indigo-600 text-[10px] font-bold text-white">
                            D
                        </span>
                        <span className="truncate text-xs font-semibold text-gray-700 dark:text-gray-200">
                            Duka la Asha
                        </span>
                    </div>
                    <div className="space-y-0.5">
                        {PREVIEW_NAV.map((item) => (
                            <div
                                key={item.label}
                                className={`flex items-center gap-2 rounded-md px-2 py-1.5 text-xs ${
                                    item.active
                                        ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300'
                                        : 'text-gray-500 dark:text-gray-400'
                                }`}
                            >
                                <item.icon className="h-3.5 w-3.5 shrink-0" />
                                <span className="truncate">{item.label}</span>
                            </div>
                        ))}
                    </div>
                </aside>

                <div className="min-w-0 flex-1 p-4 sm:p-6">
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {PREVIEW_STATS.map((stat) => (
                            <div
                                key={stat.label}
                                className="rounded-lg border border-gray-100 p-3 transition-shadow hover:shadow-md dark:border-gray-700"
                            >
                                <p className="truncate text-[11px] text-gray-500 dark:text-gray-400">
                                    {stat.label}
                                </p>
                                <p
                                    className={`mt-1.5 text-lg font-bold tracking-tight ${stat.valueTone}`}
                                >
                                    {stat.value}
                                </p>
                                <p
                                    className={`mt-0.5 truncate text-[11px] ${stat.deltaTone}`}
                                >
                                    {stat.delta}
                                </p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-3 grid gap-3 lg:grid-cols-5">
                        {/* Bars, not a gradient. Plain divs with percentage
                            heights — a chart library on a marketing page
                            costs more to ship than the picture is worth. */}
                        <div className="rounded-lg border border-gray-100 p-4 dark:border-gray-700 lg:col-span-3">
                            <p className="text-xs font-medium text-gray-700 dark:text-gray-200">
                                Sales this week
                            </p>
                            <div className="mt-4 flex h-28 items-end gap-2">
                                {PREVIEW_WEEK.map((bar) => (
                                    <div
                                        key={bar.day}
                                        className="flex flex-1 flex-col items-center gap-1.5"
                                    >
                                        <div className="flex h-full w-full items-end">
                                            <div
                                                className="w-full rounded-t bg-indigo-500/80 dark:bg-indigo-400/70"
                                                style={{
                                                    height: `${bar.height}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="text-[10px] text-gray-400">
                                            {bar.day}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-lg border border-gray-100 p-4 dark:border-gray-700 lg:col-span-2">
                            <p className="text-xs font-medium text-gray-700 dark:text-gray-200">
                                Running low
                            </p>
                            <ul className="mt-3 space-y-2.5">
                                {PREVIEW_LOW_STOCK.map((item) => (
                                    <li
                                        key={item.name}
                                        className="flex items-center justify-between gap-2 text-xs"
                                    >
                                        <span className="truncate text-gray-600 dark:text-gray-300">
                                            {item.name}
                                        </span>
                                        <span className="shrink-0 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                                            {item.left}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function useScrolled(threshold = 8) {
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > threshold);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, [threshold]);

    return scrolled;
}

export default function Welcome({
    auth,
    canLogin,
    canRegister,
    plans,
    businessTypes,
    desktopApp,
    contact,
}: PageProps<{
    canLogin: boolean;
    canRegister: boolean;
    plans: SubscriptionPlan[];
    businessTypes: LandingBusinessType[];
    desktopApp: DesktopApp;
    contact: LandingContact;
}>) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const scrolled = useScrolled(
        typeof window !== 'undefined' ? window.innerHeight - 96 : 640,
    );
    const detectedPlatform = useDetectedPlatform();

    /**
     * How long the free trial actually lasts, read from the plans rather
     * than written into the copy.
     *
     * The first plan offering any trial at all, which is the one
     * registration starts a trial on. Null when no plan offers one — in
     * which case the page says "free trial" without a number instead of
     * inventing thirty days and being wrong at the point of sign-up.
     */
    const trialDays = useMemo(() => {
        const withTrial = plans.find((plan) => plan.trial_days > 0);

        return withTrial?.trial_days ?? null;
    }, [plans]);

    /**
     * Only the contact methods that were actually configured.
     *
     * Built by filtering rather than by rendering four cards and hiding
     * empty ones, so the grid has no gaps in it — and so a deployment that
     * sets nothing drops the whole block instead of showing a heading over
     * an empty row.
     */
    const contactMethods = useMemo(
        () =>
            [
                contact.whatsappUrl && {
                    icon: ChatBubbleLeftRightIcon,
                    label: 'WhatsApp',
                    value: contact.phone ?? 'Chat with us',
                    href: contact.whatsappUrl,
                    external: true,
                },
                contact.email && {
                    icon: EnvelopeIcon,
                    label: 'Email',
                    value: contact.email,
                    href: `mailto:${contact.email}`,
                    external: false,
                },
                contact.phone && {
                    icon: PhoneIcon,
                    label: 'Call us',
                    value: contact.phone,
                    // `tel:` needs the raw digits; the displayed value keeps
                    // its spaces because that is how a person reads it.
                    href: `tel:${contact.phone.replace(/[^\d+]/g, '')}`,
                    external: false,
                },
                contact.address && {
                    icon: MapPinIcon,
                    label: 'Visit us',
                    value: contact.address,
                    href: null,
                    external: false,
                },
                contact.hours && {
                    icon: ClockIcon,
                    label: 'Opening hours',
                    value: contact.hours,
                    href: null,
                    external: false,
                },
            ].filter(Boolean) as {
                icon: typeof EnvelopeIcon;
                label: string;
                value: string;
                href: string | null;
                external: boolean;
            }[],
        [contact],
    );

    // Scoped to this page and torn down on unmount. Snapping every section
    // to the viewport is right for a landing page and wrong everywhere
    // else in the app, so it must not outlive the component that wants it.
    useEffect(() => {
        document.documentElement.classList.add('snap-page');

        return () => document.documentElement.classList.remove('snap-page');
    }, []);

    return (
        <>
            <Head title="BiasharaMax — The Business Operating System for Zanzibar" />

            <div className="bg-white dark:bg-gray-900">
                {/* Header */}
                <header
                    className={`sticky top-0 z-40 border-b backdrop-blur transition-all duration-300 ${
                        scrolled
                            ? 'border-gray-200 bg-white/90 shadow-sm dark:border-gray-800 dark:bg-gray-900/90'
                            : 'border-transparent bg-transparent'
                    }`}
                >
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                        <Link href="/" className="flex items-center gap-2">
                            <ApplicationLogo className="h-8 w-auto fill-current text-indigo-600" />
                            <span
                                className={`transition-colors ${TYPE.brand} ${
                                    scrolled
                                        ? 'text-gray-900 dark:text-gray-100'
                                        : 'text-white'
                                }`}
                            >
                                BiasharaMax
                            </span>
                        </Link>

                        <nav className="hidden items-center gap-8 md:flex">
                            {NAV_LINKS.map((link) => (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    className={`relative transition-colors ${TYPE.navLink} ${
                                        scrolled
                                            ? 'text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400'
                                            : 'text-white/80 hover:text-white'
                                    }`}
                                >
                                    {link.label}
                                </a>
                            ))}
                        </nav>

                        <div className="hidden items-center gap-4 md:flex">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className={`transition-colors ${TYPE.navLink} ${
                                        scrolled
                                            ? 'text-gray-700 hover:text-indigo-600 dark:text-gray-300'
                                            : 'text-white/90 hover:text-white'
                                    }`}
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    {canLogin && (
                                        <Link
                                            href={route('login')}
                                            className={`transition-colors ${TYPE.navLink} ${
                                                scrolled
                                                    ? 'text-gray-700 hover:text-indigo-600 dark:text-gray-300'
                                                    : 'text-white/90 hover:text-white'
                                            }`}
                                        >
                                            Log in
                                        </Link>
                                    )}
                                    {canRegister && (
                                        <Link href={route('register')}>
                                            <PrimaryButton>
                                                Start free trial
                                            </PrimaryButton>
                                        </Link>
                                    )}
                                </>
                            )}
                        </div>

                        <button
                            type="button"
                            className={`-m-2.5 p-2.5 transition-colors md:hidden ${
                                scrolled
                                    ? 'text-gray-700 dark:text-gray-300'
                                    : 'text-white'
                            }`}
                            onClick={() => setMobileMenuOpen(true)}
                        >
                            <Bars3Icon className="h-6 w-6" />
                        </button>
                    </div>

                    <AnimatePresence>
                        {mobileMenuOpen && (
                            <motion.div
                                initial={{ opacity: 0, x: 40 }}
                                animate={{ opacity: 1, x: 0 }}
                                exit={{ opacity: 0, x: 40 }}
                                transition={{ duration: 0.25, ease: 'easeOut' }}
                                className="fixed inset-0 z-50 bg-white px-6 py-4 dark:bg-gray-900 md:hidden"
                            >
                                <div className="flex items-center justify-between">
                                    <Link
                                        href="/"
                                        className="flex items-center gap-2"
                                    >
                                        <ApplicationLogo className="h-8 w-auto fill-current text-indigo-600" />
                                        <span
                                            className={`text-gray-900 dark:text-gray-100 ${TYPE.brand}`}
                                        >
                                            BiasharaMax
                                        </span>
                                    </Link>
                                    <button
                                        type="button"
                                        className="-m-2.5 p-2.5 text-gray-700 dark:text-gray-300"
                                        onClick={() => setMobileMenuOpen(false)}
                                    >
                                        <XMarkIcon className="h-6 w-6" />
                                    </button>
                                </div>
                                <div className="mt-8 space-y-4">
                                    {NAV_LINKS.map((link) => (
                                        <a
                                            key={link.href}
                                            href={link.href}
                                            onClick={() =>
                                                setMobileMenuOpen(false)
                                            }
                                            className={`block text-gray-700 dark:text-gray-300 ${TYPE.navLink}`}
                                        >
                                            {link.label}
                                        </a>
                                    ))}
                                    <div className="border-t border-gray-200 pt-4 dark:border-gray-700">
                                        {canLogin && (
                                            <Link
                                                href={route('login')}
                                                className={`block py-2 text-gray-700 dark:text-gray-300 ${TYPE.navLink}`}
                                            >
                                                Log in
                                            </Link>
                                        )}
                                        {canRegister && (
                                            <Link
                                                href={route('register')}
                                                className="mt-2 block"
                                            >
                                                <PrimaryButton className="w-full justify-center">
                                                    Start free trial
                                                </PrimaryButton>
                                            </Link>
                                        )}
                                    </div>
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </header>

                {/* Hero */}
                <section className="relative -mt-[73px] flex h-screen min-h-[600px] w-full snap-start snap-always items-center justify-center overflow-hidden bg-gray-950">
                    {/* Simulated animated "video" background */}
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 z-0 overflow-hidden"
                    >
                        <div className="absolute inset-0 bg-gradient-to-br from-indigo-950 via-gray-950 to-gray-900" />

                        <motion.div
                            animate={{
                                x: [0, 60, -20, 0],
                                y: [0, -40, 20, 0],
                            }}
                            transition={{
                                duration: 20,
                                repeat: Infinity,
                                ease: 'easeInOut',
                            }}
                            className="absolute -left-32 -top-20 h-[32rem] w-[32rem] rounded-full bg-indigo-500/50 blur-3xl"
                        />
                        <motion.div
                            animate={{
                                x: [0, -50, 30, 0],
                                y: [0, 50, -20, 0],
                            }}
                            transition={{
                                duration: 24,
                                repeat: Infinity,
                                ease: 'easeInOut',
                            }}
                            className="absolute right-0 top-1/4 h-[30rem] w-[30rem] rounded-full bg-emerald-500/40 blur-3xl"
                        />
                        <motion.div
                            animate={{
                                x: [0, 40, -30, 0],
                                y: [0, -30, 30, 0],
                            }}
                            transition={{
                                duration: 22,
                                repeat: Infinity,
                                ease: 'easeInOut',
                                delay: 3,
                            }}
                            className="absolute bottom-0 left-1/3 h-96 w-96 rounded-full bg-orange-500/35 blur-3xl"
                        />

                        {/* Drifting grid to suggest camera motion */}
                        <motion.div
                            animate={{
                                backgroundPosition: ['0px 0px', '64px 64px'],
                            }}
                            transition={{
                                duration: 10,
                                repeat: Infinity,
                                ease: 'linear',
                            }}
                            className="absolute inset-0 opacity-[0.06] [background-image:linear-gradient(to_right,white_1px,transparent_1px),linear-gradient(to_bottom,white_1px,transparent_1px)] [background-size:56px_56px]"
                        />

                        {/* Floating business icons */}
                        {HERO_FLOATING_ICONS.map(
                            (
                                { icon: Icon, className, duration, delay },
                                index,
                            ) => (
                                <motion.div
                                    key={index}
                                    animate={{
                                        y: [0, -18, 0],
                                        opacity: [0.25, 0.5, 0.25],
                                    }}
                                    transition={{
                                        duration,
                                        repeat: Infinity,
                                        ease: 'easeInOut',
                                        delay,
                                    }}
                                    className={`absolute ${className}`}
                                >
                                    <Icon className="h-10 w-10 text-white/60 sm:h-14 sm:w-14" />
                                </motion.div>
                            ),
                        )}
                    </div>

                    {/* Overlay for legibility */}
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 z-0 bg-gradient-to-b from-black/40 via-black/35 to-gray-950/85"
                    />

                    <FadeInSection className="relative z-10 mx-auto max-w-3xl px-6 text-center">
                        <motion.p
                            variants={fadeUpItem}
                            className="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-white ring-1 ring-inset ring-white/20 backdrop-blur"
                        >
                            30 days free &middot; No credit card required
                        </motion.p>
                        <motion.h1
                            variants={fadeUpItem}
                            className={`mt-6 text-white ${TYPE.heroTitle}`}
                        >
                            The complete Business Operating System
                        </motion.h1>
                        <motion.p
                            variants={fadeUpItem}
                            className={`mt-6 text-gray-200 ${TYPE.heroLede}`}
                        >
                            Inventory, POS, purchasing, CRM, accounting and your
                            own business website &mdash; all in one platform.
                            Built for retail, restaurants, pharmacies and
                            service businesses. Works online and offline.
                        </motion.p>
                        <motion.div
                            variants={fadeUpItem}
                            className="mt-10 flex items-center justify-center gap-4"
                        >
                            {canRegister && (
                                <Link href={route('register')}>
                                    <motion.span
                                        whileHover={{ scale: 1.04 }}
                                        whileTap={{ scale: 0.98 }}
                                        className={`inline-flex items-center rounded-md bg-indigo-500 px-6 py-3 text-white ${TYPE.button} shadow-lg shadow-indigo-950/50 transition-colors hover:bg-indigo-400`}
                                    >
                                        Start your free trial
                                    </motion.span>
                                </Link>
                            )}
                            <a
                                href="#pricing"
                                className={`text-white/90 hover:text-white ${TYPE.button}`}
                            >
                                See pricing &rarr;
                            </a>
                        </motion.div>
                    </FadeInSection>

                    {/* Scroll cue */}
                    <motion.a
                        href="#preview"
                        aria-hidden="true"
                        animate={{ y: [0, 8, 0] }}
                        transition={{
                            duration: 1.8,
                            repeat: Infinity,
                            ease: 'easeInOut',
                        }}
                        className="absolute bottom-8 left-1/2 z-10 -translate-x-1/2 text-white/70 transition-colors hover:text-white"
                    >
                        <ChevronDownIcon className="h-6 w-6" />
                    </motion.a>
                </section>

                {/* Product preview */}
                <SnapSection id="preview" className="bg-white dark:bg-gray-900">
                    <div className="w-full">
                        <FadeInSection>
                            <motion.div variants={fadeUpItem}>
                                <SectionHeading
                                    eyebrow="A look inside"
                                    title="Your whole shop on one screen"
                                    description="Takings, debts, stock and what needs reordering — the dashboard you land on every morning."
                                />
                            </motion.div>

                            <motion.div
                                variants={fadeUpItem}
                                whileHover={{ y: -4 }}
                                transition={{
                                    type: 'spring',
                                    stiffness: 200,
                                    damping: 20,
                                }}
                                className="mx-auto mt-10 max-w-5xl"
                            >
                                <DashboardPreview />
                            </motion.div>
                        </FadeInSection>
                    </div>
                </SnapSection>

                {/* Features */}
                <SnapSection
                    id="features"
                    className="bg-gray-50 dark:bg-gray-950/40"
                >
                    <div>
                        <FadeInSection>
                            <motion.div variants={fadeUpItem}>
                                <SectionHeading
                                    eyebrow="Everything in one place"
                                    title="Run your entire business from one platform"
                                    description="Stop juggling separate apps and notebooks. BiasharaMax brings every part of your business operations together."
                                />
                            </motion.div>

                            {/*
                              Nine cards in a fixed screen height, so this
                              grid is tighter than the rest of the page:
                              `gap-6` not `gap-8`, and it commits to three
                              columns from `sm` rather than stepping 1→2→3.
                              Two columns would make five rows, which is one
                              row more than the space allows.
                            */}
                            <div className="mx-auto mt-8 grid max-w-5xl grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                                {FEATURES.map((feature) => (
                                    <motion.div
                                        key={feature.title}
                                        variants={fadeUpItem}
                                        whileHover={{ y: -4 }}
                                        transition={{
                                            type: 'spring',
                                            stiffness: 300,
                                            damping: 20,
                                        }}
                                        className="flex gap-3"
                                    >
                                        <div className="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-indigo-600">
                                            <feature.icon
                                                className="h-5 w-5 text-white"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <div>
                                            <h3
                                                className={`text-gray-900 dark:text-gray-100 ${TYPE.cardTitle}`}
                                            >
                                                {feature.title}
                                            </h3>
                                            <p
                                                className={`mt-1.5 text-gray-600 dark:text-gray-400 ${TYPE.cardBody}`}
                                            >
                                                {feature.description}
                                            </p>
                                        </div>
                                    </motion.div>
                                ))}
                            </div>
                        </FadeInSection>
                    </div>
                </SnapSection>

                {/* Business types */}
                <SnapSection id="businesses">
                    <div>
                        <FadeInSection>
                            <motion.div variants={fadeUpItem}>
                                <SectionHeading
                                    eyebrow="Built for businesses like yours"
                                    title="One platform, every kind of business"
                                    description="Stock, sales, staff and books work the same whether you run a duka, a pharmacy or a workshop."
                                />
                            </motion.div>

                            {/*
                              Tiles rather than pills. Eleven small
                              capsules occupied two thin lines in the
                              middle of a full-height section and left the
                              rest of the viewport empty; a grid fills the
                              space it was already taking up.
                            */}
                            <div className="mx-auto mt-12 grid max-w-5xl grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                {businessTypes.map((type) => {
                                    const Icon =
                                        BUSINESS_TYPE_ICONS[type.icon ?? ''] ??
                                        BuildingStorefrontIcon;

                                    return (
                                        <motion.div
                                            key={type.slug}
                                            variants={fadeUpItem}
                                            whileHover={{ y: -3 }}
                                            className="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3.5 transition-colors hover:border-indigo-300 dark:border-gray-700 dark:bg-gray-800/40 dark:hover:border-indigo-500/50"
                                        >
                                            {/*
                                              Each type carries its own
                                              colour in the admin table.
                                              The tint is applied inline
                                              because the value is data, not
                                              one of a known set — an
                                              interpolated Tailwind class
                                              would not survive the purge.
                                            */}
                                            <span
                                                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                                                style={{
                                                    backgroundColor: `${type.color ?? '#4F46E5'}1A`,
                                                }}
                                            >
                                                <Icon
                                                    className="h-5 w-5"
                                                    style={{
                                                        color:
                                                            type.color ??
                                                            '#4F46E5',
                                                    }}
                                                />
                                            </span>
                                            <span
                                                className={`min-w-0 font-medium text-gray-800 dark:text-gray-200 ${TYPE.cardBody}`}
                                            >
                                                {type.name}
                                            </span>
                                        </motion.div>
                                    );
                                })}
                            </div>

                            <motion.p
                                variants={fadeUpItem}
                                className={`mt-8 text-center text-gray-500 dark:text-gray-400 ${TYPE.cardBody}`}
                            >
                                Not on the list? Pick{' '}
                                <span className="font-medium text-gray-700 dark:text-gray-300">
                                    Other
                                </span>{' '}
                                when you sign up — nothing here is limited to
                                one trade.
                            </motion.p>
                        </FadeInSection>
                    </div>
                </SnapSection>

                {/* How it works */}
                <SnapSection className="bg-gray-50 dark:bg-gray-950/40">
                    <div>
                        <FadeInSection>
                            <motion.div variants={fadeUpItem}>
                                <SectionHeading
                                    eyebrow="Getting started"
                                    title="Up and running in minutes"
                                    description="No installation, no consultant, no data migration project."
                                />
                            </motion.div>

                            <div className="relative mx-auto mt-14 max-w-5xl">
                                {/*
                                  The line that makes three items read as a
                                  sequence. Without it they were three
                                  unrelated paragraphs that happened to be
                                  numbered. Inset by a sixth at each end so
                                  it runs between the icons rather than out
                                  past them, and hidden on stacked layouts
                                  where the steps sit vertically.
                                */}
                                <div
                                    aria-hidden="true"
                                    className="absolute left-[16.6%] right-[16.6%] top-8 hidden border-t-2 border-dashed border-indigo-200 dark:border-indigo-500/30 sm:block"
                                />

                                <div className="relative grid gap-10 sm:grid-cols-3 sm:gap-6">
                                    {HOW_IT_WORKS.map((item) => (
                                        <motion.div
                                            key={item.step}
                                            variants={fadeUpItem}
                                            className="flex flex-col items-center text-center"
                                        >
                                            <div className="relative flex h-16 w-16 items-center justify-center rounded-2xl border border-indigo-100 bg-white shadow-sm dark:border-indigo-500/30 dark:bg-gray-900">
                                                <item.icon className="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                                                <span className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">
                                                    {item.step}
                                                </span>
                                            </div>
                                            <h3
                                                className={`mt-5 text-gray-900 dark:text-gray-100 ${TYPE.cardTitle}`}
                                            >
                                                {item.title}
                                            </h3>
                                            <p
                                                className={`mt-2 max-w-xs text-gray-600 dark:text-gray-400 ${TYPE.cardBody}`}
                                            >
                                                {item.description(trialDays)}
                                            </p>
                                            <p
                                                className={`mt-3 max-w-xs text-gray-500 ${TYPE.meta}`}
                                            >
                                                {item.detail}
                                            </p>
                                        </motion.div>
                                    ))}
                                </div>
                            </div>

                            {/*
                              The section is called "Getting started" and
                              had nothing to start with. Someone convinced
                              by three steps had to scroll back up to act
                              on them.
                            */}
                            {canRegister && (
                                <motion.div
                                    variants={fadeUpItem}
                                    className="mt-14 text-center"
                                >
                                    <Link href={route('register')}>
                                        <PrimaryButton className="px-6 py-3">
                                            Create your business
                                        </PrimaryButton>
                                    </Link>
                                    <p
                                        className={`mt-3 text-gray-500 dark:text-gray-400 ${TYPE.meta}`}
                                    >
                                        {trialDays === null
                                            ? 'Free trial. No card required.'
                                            : `${trialDays} days free. No card required.`}
                                    </p>
                                </motion.div>
                            )}
                        </FadeInSection>
                    </div>
                </SnapSection>

                {/* Download the desktop till */}
                <section
                    id="download"
                    className="relative flex w-full snap-start snap-always items-center overflow-hidden bg-gray-900 py-16 dark:bg-gray-950 sm:h-screen sm:min-h-[600px] sm:py-12 sm:pt-[73px]"
                >
                    {/* Same soft glow the hero uses, kept subtle so the
                        cards stay the brightest thing in the section. */}
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0"
                    >
                        <div className="absolute -top-40 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-indigo-600/20 blur-3xl" />
                    </div>

                    <div className="relative mx-auto max-w-7xl px-6">
                        <FadeInSection>
                            <motion.div
                                variants={fadeUpItem}
                                className="text-center"
                            >
                                <p
                                    className={`text-indigo-400 ${TYPE.eyebrow}`}
                                >
                                    Desktop till
                                </p>
                                <h2
                                    className={`mt-3 text-white ${TYPE.sectionTitle}`}
                                >
                                    Keep selling when the internet stops
                                </h2>
                                <p
                                    className={`mx-auto mt-3 max-w-2xl text-gray-400 ${TYPE.sectionLede}`}
                                >
                                    The BiasharaMax desktop app runs your
                                    counter offline and syncs every sale back
                                    the moment you reconnect. Install it on as
                                    many tills as your licence allows.
                                </p>

                                {(desktopApp.version ||
                                    desktopApp.releasedAt) && (
                                    <div
                                        className={`mt-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-gray-500 ${TYPE.meta}`}
                                    >
                                        {desktopApp.version && (
                                            <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 font-medium text-gray-300">
                                                Version {desktopApp.version}
                                            </span>
                                        )}
                                        {desktopApp.releasedAt && (
                                            <span>
                                                Updated{' '}
                                                {new Date(
                                                    desktopApp.releasedAt,
                                                ).toLocaleDateString(
                                                    undefined,
                                                    {
                                                        day: 'numeric',
                                                        month: 'short',
                                                        year: 'numeric',
                                                    },
                                                )}
                                            </span>
                                        )}
                                        {desktopApp.releaseNotesUrl && (
                                            <a
                                                href={
                                                    desktopApp.releaseNotesUrl
                                                }
                                                className="font-medium text-indigo-400 underline-offset-4 hover:underline"
                                            >
                                                Release notes
                                            </a>
                                        )}
                                    </div>
                                )}
                            </motion.div>

                            <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                {desktopApp.platforms.map((platform) => {
                                    const Icon = PLATFORM_ICONS[platform.key];
                                    const isDetected =
                                        detectedPlatform === platform.key;
                                    const isReady = Boolean(platform.url);

                                    return (
                                        <motion.div
                                            key={platform.key}
                                            variants={fadeUpItem}
                                            whileHover={
                                                isReady ? { y: -6 } : undefined
                                            }
                                            transition={{
                                                type: 'spring',
                                                stiffness: 250,
                                                damping: 20,
                                            }}
                                            className={`relative flex flex-col rounded-2xl border p-6 backdrop-blur ${
                                                isDetected
                                                    ? 'border-indigo-500 bg-white/10 ring-2 ring-indigo-500'
                                                    : 'border-white/10 bg-white/5'
                                            }`}
                                        >
                                            {isDetected && (
                                                <p
                                                    className={`absolute -top-3 left-8 inline-flex items-center rounded-full bg-indigo-600 px-3 py-1 text-white ${TYPE.badge}`}
                                                >
                                                    Your system
                                                </p>
                                            )}

                                            <Icon
                                                className="h-8 w-8 text-indigo-400"
                                                aria-hidden="true"
                                            />

                                            <h3
                                                className={`mt-4 text-white ${TYPE.cardTitle}`}
                                            >
                                                {platform.name}
                                            </h3>
                                            <p
                                                className={`mt-1 text-gray-400 ${TYPE.cardBody}`}
                                            >
                                                {platform.requirement}
                                            </p>

                                            <dl
                                                className={`mt-4 space-y-1.5 ${TYPE.meta}`}
                                            >
                                                <div className="flex justify-between gap-4">
                                                    <dt className="text-gray-500">
                                                        Format
                                                    </dt>
                                                    <dd className="text-gray-300">
                                                        {platform.format}
                                                    </dd>
                                                </div>
                                                {platform.size && (
                                                    <div className="flex justify-between gap-4">
                                                        <dt className="text-gray-500">
                                                            Size
                                                        </dt>
                                                        <dd className="text-gray-300">
                                                            {platform.size}
                                                        </dd>
                                                    </div>
                                                )}
                                            </dl>

                                            {/* Pushes the button to the bottom so
                                                all three align regardless of how
                                                much metadata each card carries. */}
                                            <div className="mt-5 flex-1" />

                                            {isReady ? (
                                                <a
                                                    href={
                                                        platform.url as string
                                                    }
                                                    className={`inline-flex w-full items-center justify-center gap-2 rounded-xl px-5 py-3 transition ${TYPE.button} ${
                                                        isDetected
                                                            ? 'bg-indigo-600 text-white hover:bg-indigo-500'
                                                            : 'bg-white/10 text-white hover:bg-white/20'
                                                    }`}
                                                >
                                                    <ArrowDownTrayIcon
                                                        className="h-5 w-5"
                                                        aria-hidden="true"
                                                    />
                                                    Download for {platform.name}
                                                </a>
                                            ) : (
                                                <span
                                                    // A disabled span, not a
                                                    // disabled button: there is
                                                    // nothing here to activate, so
                                                    // it should not be focusable or
                                                    // announced as a control.
                                                    className={`inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-dashed border-white/15 px-5 py-3 text-gray-500 ${TYPE.button}`}
                                                >
                                                    Coming soon
                                                </span>
                                            )}

                                            {platform.checksum && (
                                                <p className="mt-4 break-all text-center font-mono text-[11px] leading-relaxed text-gray-600">
                                                    SHA-256 {platform.checksum}
                                                </p>
                                            )}
                                        </motion.div>
                                    );
                                })}
                            </div>

                            <motion.p
                                variants={fadeUpItem}
                                className={`mx-auto mt-8 max-w-2xl text-center text-gray-500 ${TYPE.meta}`}
                            >
                                {desktopApp.isAvailable ? (
                                    <>
                                        You’ll need your licence key to activate
                                        a till. It’s on your{' '}
                                        <Link
                                            href={
                                                canLogin ? route('login') : '#'
                                            }
                                            className="font-medium text-indigo-400 underline-offset-4 hover:underline"
                                        >
                                            subscription page
                                        </Link>{' '}
                                        after signing in.
                                    </>
                                ) : (
                                    <>
                                        Desktop builds are being prepared.
                                        Everything BiasharaMax does works in
                                        your browser today — the desktop till
                                        adds offline selling.
                                    </>
                                )}
                            </motion.p>
                        </FadeInSection>
                    </div>
                </section>

                {/* Pricing */}
                <SnapSection id="pricing">
                    <div>
                        <FadeInSection>
                            <motion.div variants={fadeUpItem}>
                                <SectionHeading
                                    eyebrow="Simple pricing"
                                    title="Plans that grow with your business"
                                    description="Every plan starts with a 30-day free trial. No setup fees, cancel anytime."
                                />
                            </motion.div>

                            <div className="mx-auto mt-10 grid max-w-5xl gap-6 sm:grid-cols-3">
                                {plans.map((plan, index) => (
                                    <motion.div
                                        key={plan.id}
                                        variants={fadeUpItem}
                                        whileHover={{ y: -6 }}
                                        transition={{
                                            type: 'spring',
                                            stiffness: 250,
                                            damping: 20,
                                        }}
                                        className={`flex flex-col rounded-2xl border p-6 ${
                                            index === 1
                                                ? 'border-indigo-600 shadow-lg ring-2 ring-indigo-600'
                                                : 'border-gray-200 dark:border-gray-700'
                                        }`}
                                    >
                                        {index === 1 && (
                                            <p
                                                className={`mb-3 inline-flex items-center self-start rounded-full bg-indigo-600 px-3 py-1 text-white ${TYPE.badge}`}
                                            >
                                                Most popular
                                            </p>
                                        )}
                                        <h3
                                            className={`text-gray-900 dark:text-gray-100 ${TYPE.cardTitle}`}
                                        >
                                            {plan.name}
                                        </h3>
                                        <p
                                            className={`mt-1 line-clamp-2 text-gray-500 dark:text-gray-400 ${TYPE.cardBody}`}
                                        >
                                            {plan.description}
                                        </p>
                                        <p className="mt-4">
                                            <span
                                                className={`text-gray-900 dark:text-gray-100 ${TYPE.price}`}
                                            >
                                                TZS{' '}
                                                {formatCurrency(
                                                    plan.price_monthly,
                                                )}
                                            </span>
                                            <span
                                                className={`text-gray-500 dark:text-gray-400 ${TYPE.meta}`}
                                            >
                                                /month
                                            </span>
                                        </p>
                                        {/*
                                          Capped, because `features` comes
                                          from the plans table and nothing
                                          stops an admin adding fifteen. In
                                          a fixed-height section an
                                          unbounded list is the one thing
                                          that can push the "Start free
                                          trial" button off the screen —
                                          and that button is the only
                                          reason this section exists.
                                        */}
                                        {/*
                                            `features` is a nullable JSON
                                            column. A plan saved without any
                                            arrives as null, and reading
                                            `.slice`/`.length` on it takes
                                            the entire landing page down —
                                            the public one, for every
                                            visitor, not just the customer
                                            who owns that plan.
                                        */}
                                        <ul className="mt-4 space-y-2">
                                            {(plan.features ?? [])
                                                .slice(0, PLAN_FEATURE_LIMIT)
                                                .map((feature) => (
                                                    <li
                                                        key={feature}
                                                        className={`flex gap-2 text-gray-600 dark:text-gray-400 ${TYPE.cardBody}`}
                                                    >
                                                        <CheckIcon className="h-5 w-5 flex-none text-indigo-600" />
                                                        <span className="line-clamp-1">
                                                            {feature}
                                                        </span>
                                                    </li>
                                                ))}
                                        </ul>

                                        {(plan.features ?? []).length >
                                            PLAN_FEATURE_LIMIT && (
                                            <p
                                                className={`mt-2 font-medium text-indigo-600 dark:text-indigo-400 ${TYPE.meta}`}
                                            >
                                                +{' '}
                                                {(plan.features ?? []).length -
                                                    PLAN_FEATURE_LIMIT}{' '}
                                                more
                                            </p>
                                        )}

                                        {/* Bottom-aligns the button across
                                            all three cards regardless of
                                            how many features each lists. */}
                                        <div className="flex-1" />

                                        {canRegister && (
                                            <Link
                                                href={route('register')}
                                                className="mt-6 block"
                                            >
                                                <PrimaryButton className="w-full justify-center">
                                                    Start free trial
                                                </PrimaryButton>
                                            </Link>
                                        )}
                                    </motion.div>
                                ))}
                            </div>
                        </FadeInSection>
                    </div>
                </SnapSection>

                {/*
                  Final CTA and footer share the last screen.
                  A footer given a full viewport to itself reads as an empty
                  page the visitor has scrolled into by mistake. Here the CTA
                  takes the space it needs and the footer sits at the bottom
                  of the same panel, which is where a footer belongs.

                  Near-black rather than a flood of brand indigo. A whole
                  screen of saturated colour leaves nothing for the button
                  to stand out against — every element ends up the same
                  weight, which is why the previous version read as a
                  headline floating in a blue void. On a dark ground the
                  one indigo element is the one you are meant to press.
                */}
                <section className="relative flex w-full snap-start snap-always flex-col overflow-hidden bg-gray-950 sm:h-screen sm:min-h-[600px]">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 overflow-hidden"
                    >
                        <div className="absolute -top-32 left-1/2 h-96 w-[46rem] -translate-x-1/2 rounded-full bg-indigo-600/20 blur-3xl" />
                        <div className="absolute -bottom-40 -right-20 h-80 w-80 rounded-full bg-indigo-500/10 blur-3xl" />
                    </div>

                    <FadeInSection className="relative mx-auto flex w-full max-w-3xl flex-1 flex-col justify-center px-6 py-20 text-center">
                        <motion.p
                            variants={fadeUpItem}
                            className={`mx-auto rounded-full bg-indigo-500/15 px-4 py-1.5 text-indigo-300 ${TYPE.eyebrow}`}
                        >
                            Ready when you are
                        </motion.p>

                        <motion.h2
                            variants={fadeUpItem}
                            className={`mt-6 text-white ${TYPE.sectionTitle}`}
                        >
                            Stop guessing what your shop made today.
                        </motion.h2>

                        <motion.p
                            variants={fadeUpItem}
                            className={`mx-auto mt-5 max-w-xl text-gray-400 ${TYPE.sectionLede}`}
                        >
                            {/*
                              "Join businesses running on BiasharaMax" was a
                              number-shaped sentence with no number in it,
                              which invites the reader to ask how many and
                              notice nobody said. These are all checkable.
                            */}
                            {trialDays === null
                                ? 'Free while you try it. No card, no contract, no setup fee.'
                                : `Free for ${trialDays} days. No card, no contract, no setup fee.`}
                        </motion.p>

                        {/*
                          Two actions, not one. Someone at the bottom of a
                          landing page is either ready to sign up or has a
                          question, and the previous version served only the
                          first — the second had to go hunting.
                        */}
                        <motion.div
                            variants={fadeUpItem}
                            className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
                        >
                            {canRegister && (
                                <Link href={route('register')}>
                                    <motion.span
                                        whileHover={{ scale: 1.03 }}
                                        whileTap={{ scale: 0.98 }}
                                        className={`inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3.5 ${TYPE.button} text-white shadow-lg shadow-indigo-600/25 transition-colors hover:bg-indigo-500`}
                                    >
                                        Start your free trial
                                        <ArrowRightIcon className="h-4 w-4" />
                                    </motion.span>
                                </Link>
                            )}

                            {contact.whatsappUrl && (
                                <motion.a
                                    href={contact.whatsappUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    whileHover={{ scale: 1.03 }}
                                    whileTap={{ scale: 0.98 }}
                                    className={`inline-flex items-center gap-2 rounded-xl border border-white/20 px-6 py-3.5 ${TYPE.button} text-white transition-colors hover:border-white/40 hover:bg-white/5`}
                                >
                                    <ChatBubbleLeftRightIcon className="h-4 w-4" />
                                    Talk to us on WhatsApp
                                </motion.a>
                            )}
                        </motion.div>

                        {/*
                          Each of these answers an objection that otherwise
                          stops the click. Only rendered when true — the
                          trial length is read from the plans, so a shorter
                          trial cannot leave a stale promise behind.
                        */}
                        <motion.ul
                            variants={fadeUpItem}
                            className={`mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-gray-400 ${TYPE.cardBody}`}
                        >
                            {[
                                trialDays === null
                                    ? 'Free trial'
                                    : `${trialDays}-day free trial`,
                                'No credit card',
                                'Cancel anytime',
                            ].map((item) => (
                                <li
                                    key={item}
                                    className="flex items-center gap-2"
                                >
                                    <span className="h-1.5 w-1.5 rounded-full bg-indigo-400" />
                                    {item}
                                </li>
                            ))}
                        </motion.ul>
                    </FadeInSection>

                    {/*
                      A real footer with columns, rather than one row of
                      three centred items. Contact lives here in its own
                      column — which is the first place anyone looks for it,
                      and it stops the contact details competing with the
                      call to action directly above.
                    */}
                    <footer className="relative border-t border-white/10">
                        <div className="mx-auto max-w-7xl px-6 py-10">
                            <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="lg:col-span-2">
                                    <div className="flex items-center gap-2">
                                        <ApplicationLogo className="h-7 w-auto fill-current text-white" />
                                        <span
                                            className={`text-white ${TYPE.brand}`}
                                        >
                                            BiasharaMax
                                        </span>
                                    </div>
                                    <p
                                        className={`mt-4 max-w-xs text-gray-400 ${TYPE.cardBody}`}
                                    >
                                        Till, stock, customers, staff and books
                                        — one platform that runs a business.
                                        Works offline.
                                    </p>
                                </div>

                                <div>
                                    <p
                                        className={`text-gray-500 ${TYPE.eyebrow}`}
                                    >
                                        Product
                                    </p>
                                    <ul
                                        className={`mt-4 space-y-3 ${TYPE.cardBody}`}
                                    >
                                        {NAV_LINKS.map((link) => (
                                            <li key={link.href}>
                                                <a
                                                    href={link.href}
                                                    className="text-gray-400 transition-colors hover:text-white"
                                                >
                                                    {link.label}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <div>
                                    <p
                                        className={`text-gray-500 ${TYPE.eyebrow}`}
                                    >
                                        Get in touch
                                    </p>
                                    <ul
                                        className={`mt-4 space-y-3 ${TYPE.cardBody}`}
                                    >
                                        {contactMethods.map((method) => (
                                            <li key={method.label}>
                                                {method.href ? (
                                                    <a
                                                        href={method.href}
                                                        target={
                                                            method.external
                                                                ? '_blank'
                                                                : undefined
                                                        }
                                                        rel={
                                                            method.external
                                                                ? 'noopener noreferrer'
                                                                : undefined
                                                        }
                                                        className="flex items-start gap-2.5 text-gray-400 transition-colors hover:text-white"
                                                    >
                                                        <method.icon className="mt-0.5 h-4 w-4 shrink-0" />
                                                        <span>
                                                            {method.value}
                                                        </span>
                                                    </a>
                                                ) : (
                                                    // Not a link, so not
                                                    // styled as one. An
                                                    // address that lights up
                                                    // on hover costs someone
                                                    // a click for nothing.
                                                    <span className="flex items-start gap-2.5 text-gray-400">
                                                        <method.icon className="mt-0.5 h-4 w-4 shrink-0" />
                                                        <span>
                                                            {method.value}
                                                        </span>
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>

                            <div className="mt-10 flex flex-col items-center justify-between gap-3 border-t border-white/10 pt-6 sm:flex-row">
                                <p className={`text-gray-500 ${TYPE.meta}`}>
                                    &copy; {new Date().getFullYear()}{' '}
                                    BiasharaMax. All rights reserved.
                                </p>
                                <p
                                    className={`flex items-center gap-2 text-gray-500 ${TYPE.meta}`}
                                >
                                    <MapPinIcon className="h-4 w-4" />
                                    {/*
                                      Was "Built for African businesses,
                                      scalable globally" — a claim about a
                                      continent from a product serving one
                                      archipelago. A shopkeeper in Stone
                                      Town reads a continental claim as
                                      marketing; a local one as a promise
                                      that somebody nearby will pick up
                                      the phone.
                                    */}
                                    Built in Zanzibar, for Zanzibar.
                                </p>
                            </div>
                        </div>
                    </footer>
                </section>
            </div>

            {/*
              Outside the snapping container on purpose — inside it, a
              `fixed` element still paints fine but sits in a scroll
              container that snaps, and the button would flicker at every
              stop.

              Rendered only when a number is configured. A support button
              that opens nothing is worse than no button: it collects the
              click of someone who needed help and gives them silence.
            */}
            {contact.whatsappUrl && (
                <WhatsAppButton href={contact.whatsappUrl} />
            )}
        </>
    );
}
