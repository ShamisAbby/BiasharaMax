import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';

// ─── Slide content ───────────────────────────────────────────────────────────

const SLIDES = [
    {
        title: 'Your business, all in one place',
        body: 'Inventory, sales, purchasing, CRM, finance and payroll — managed from one dashboard, wherever you are.',
        icon: (
            <svg viewBox="0 0 80 80" className="h-32 w-32" fill="none">
                <rect
                    x="8"
                    y="8"
                    width="28"
                    height="28"
                    rx="6"
                    fill="#6366f1"
                    opacity=".9"
                />
                <rect
                    x="44"
                    y="8"
                    width="28"
                    height="28"
                    rx="6"
                    fill="#8b5cf6"
                    opacity=".7"
                />
                <rect
                    x="8"
                    y="44"
                    width="28"
                    height="28"
                    rx="6"
                    fill="#8b5cf6"
                    opacity=".7"
                />
                <rect
                    x="44"
                    y="44"
                    width="28"
                    height="28"
                    rx="6"
                    fill="#6366f1"
                    opacity=".5"
                />
                <path
                    d="M16 22 L28 22 M22 16 L22 28"
                    stroke="white"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                />
                <circle cx="58" cy="22" r="6" fill="white" opacity=".9" />
                <path
                    d="M52 58 L64 58 M52 52 L64 52 M52 64 L64 64"
                    stroke="white"
                    strokeWidth="2"
                    strokeLinecap="round"
                />
                <path
                    d="M18 54 L26 62 L38 48"
                    stroke="white"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        ),
    },
    {
        title: 'Sell fast, track everything',
        body: 'A touch-friendly POS, real-time stock tracking across branches, and automatic low-stock alerts keep you ahead.',
        icon: (
            <svg viewBox="0 0 80 80" className="h-32 w-32" fill="none">
                <rect
                    x="10"
                    y="14"
                    width="60"
                    height="42"
                    rx="8"
                    fill="#6366f1"
                    opacity=".15"
                />
                <rect
                    x="10"
                    y="14"
                    width="60"
                    height="42"
                    rx="8"
                    stroke="#6366f1"
                    strokeWidth="2"
                />
                <path
                    d="M22 32 L22 44 M32 24 L32 44 M42 36 L42 44 M52 28 L52 44"
                    stroke="#6366f1"
                    strokeWidth="3"
                    strokeLinecap="round"
                />
                <circle cx="22" cy="32" r="3" fill="#8b5cf6" />
                <circle cx="32" cy="24" r="3" fill="#8b5cf6" />
                <circle cx="42" cy="36" r="3" fill="#8b5cf6" />
                <circle cx="52" cy="28" r="3" fill="#8b5cf6" />
                <rect
                    x="25"
                    y="60"
                    width="30"
                    height="6"
                    rx="3"
                    fill="#6366f1"
                    opacity=".4"
                />
            </svg>
        ),
    },
    {
        title: 'Know your numbers, always',
        body: 'Full double-entry accounting, expense tracking, financial reports, and AI insights — without touching a spreadsheet.',
        icon: (
            <svg viewBox="0 0 80 80" className="h-32 w-32" fill="none">
                <circle
                    cx="40"
                    cy="40"
                    r="28"
                    fill="#6366f1"
                    opacity=".12"
                    stroke="#6366f1"
                    strokeWidth="2"
                />
                <path
                    d="M28 52 L28 34 M36 52 L36 40 M44 52 L44 28 M52 52 L52 36"
                    stroke="#6366f1"
                    strokeWidth="3"
                    strokeLinecap="round"
                />
                <path
                    d="M26 34 L44 28 L56 36"
                    stroke="#8b5cf6"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                <circle cx="26" cy="34" r="2.5" fill="#8b5cf6" />
                <circle cx="44" cy="28" r="2.5" fill="#8b5cf6" />
                <circle cx="56" cy="36" r="2.5" fill="#8b5cf6" />
            </svg>
        ),
    },
    {
        title: 'Works even offline',
        body: "Keep selling and managing stock even when the internet drops. Everything syncs automatically when you're back online.",
        icon: (
            <svg viewBox="0 0 80 80" className="h-32 w-32" fill="none">
                <circle
                    cx="40"
                    cy="40"
                    r="28"
                    fill="#6366f1"
                    opacity=".12"
                    stroke="#6366f1"
                    strokeWidth="2"
                />
                <path
                    d="M24 38 Q40 20 56 38"
                    stroke="#6366f1"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    fill="none"
                    opacity=".4"
                />
                <path
                    d="M29 43 Q40 30 51 43"
                    stroke="#6366f1"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    fill="none"
                    opacity=".7"
                />
                <circle cx="40" cy="50" r="5" fill="#6366f1" />
                <path
                    d="M14 18 L66 62"
                    stroke="#ef4444"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    opacity=".6"
                />
            </svg>
        ),
    },
];

// ─── Screen types ─────────────────────────────────────────────────────────────

type Screen = 'splash' | 'slides' | 'auth' | 'account-type' | 'license';

const slide = {
    enter: (dir: number) => ({ x: dir > 0 ? 60 : -60, opacity: 0 }),
    center: { x: 0, opacity: 1 },
    exit: (dir: number) => ({ x: dir > 0 ? -60 : 60, opacity: 0 }),
};

const fade = {
    hidden: { opacity: 0, y: 16 },
    show: { opacity: 1, y: 0, transition: { duration: 0.35 } },
    exit: { opacity: 0, y: -8, transition: { duration: 0.2 } },
};

// ─── Main component ───────────────────────────────────────────────────────────

export default function Onboarding() {
    const { auth } = usePage<PageProps>().props;

    const [screen, setScreen] = useState<Screen>('splash');
    const [slideIndex, setSlideIndex] = useState(0);
    const [slideDir, setSlideDir] = useState(1);
    const [licenseCode, setLicenseCode] = useState('');
    const [licenseError, setLicenseError] = useState('');
    const [licenseLoading, setLicenseLoading] = useState(false);
    const [validatedCode, setValidatedCode] = useState<{
        code: string;
        plan: { name: string; description: string };
        duration_months: number;
    } | null>(null);

    // If authenticated, skip straight to dashboard
    useEffect(() => {
        if (auth?.user) {
            router.visit(route('dashboard'));
        }
    }, []);

    // Splash → slides after 1.8 s
    useEffect(() => {
        if (screen !== 'splash') return;
        const t = setTimeout(() => {
            const seen = localStorage.getItem('biasharaos-onboarding-done');
            setScreen(seen ? 'auth' : 'slides');
        }, 1800);
        return () => clearTimeout(t);
    }, [screen]);

    const goToSlide = (i: number) => {
        setSlideDir(i > slideIndex ? 1 : -1);
        setSlideIndex(i);
    };

    const nextSlide = () => {
        if (slideIndex < SLIDES.length - 1) {
            goToSlide(slideIndex + 1);
        } else {
            localStorage.setItem('biasharaos-onboarding-done', '1');
            setScreen('auth');
        }
    };

    const skip = () => {
        localStorage.setItem('biasharaos-onboarding-done', '1');
        setScreen('auth');
    };

    const validateLicense = async () => {
        setLicenseError('');
        setLicenseLoading(true);
        try {
            const res = await fetch(route('registration-codes.validate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content ?? '',
                },
                body: JSON.stringify({
                    code: licenseCode.trim().toUpperCase(),
                }),
            });
            const data = await res.json();
            if (data.valid) {
                setValidatedCode(data);
            } else {
                setLicenseError(data.reason ?? 'Invalid code.');
            }
        } catch {
            setLicenseError('Could not validate code. Please try again.');
        } finally {
            setLicenseLoading(false);
        }
    };

    const proceedWithCode = () => {
        if (!validatedCode) return;
        router.visit(route('register.license', { code: validatedCode.code }));
    };

    if (screen === 'splash') return <SplashScreen />;

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-indigo-950 via-indigo-900 to-violet-900 px-6 py-12">
            <AnimatePresence mode="wait">
                {screen === 'slides' && (
                    <motion.div
                        key="slides"
                        variants={fade}
                        initial="hidden"
                        animate="show"
                        exit="exit"
                        className="w-full max-w-sm"
                    >
                        {/* Slide content */}
                        <div className="relative overflow-hidden rounded-3xl bg-white/10 p-8 text-center ring-1 ring-white/20 backdrop-blur-md">
                            <AnimatePresence custom={slideDir} mode="wait">
                                <motion.div
                                    key={slideIndex}
                                    custom={slideDir}
                                    variants={slide}
                                    initial="enter"
                                    animate="center"
                                    exit="exit"
                                    transition={{ duration: 0.3 }}
                                >
                                    <div className="mb-6 flex justify-center">
                                        {SLIDES[slideIndex].icon}
                                    </div>
                                    <h2 className="text-2xl font-bold text-white">
                                        {SLIDES[slideIndex].title}
                                    </h2>
                                    <p className="mt-3 text-sm leading-relaxed text-indigo-200">
                                        {SLIDES[slideIndex].body}
                                    </p>
                                </motion.div>
                            </AnimatePresence>

                            {/* Dots */}
                            <div className="mt-8 flex justify-center gap-2">
                                {SLIDES.map((_, i) => (
                                    <button
                                        key={i}
                                        onClick={() => goToSlide(i)}
                                        className={`h-2 rounded-full transition-all duration-300 ${
                                            i === slideIndex
                                                ? 'w-6 bg-white'
                                                : 'w-2 bg-white/30'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>

                        {/* Navigation */}
                        <div className="mt-6 flex items-center justify-between">
                            <button
                                onClick={skip}
                                className="text-sm text-indigo-300 hover:text-white"
                            >
                                Skip
                            </button>
                            <button
                                onClick={nextSlide}
                                className="rounded-xl bg-indigo-500 px-6 py-2.5 text-sm font-semibold text-white shadow-lg hover:bg-indigo-400 active:scale-95"
                            >
                                {slideIndex === SLIDES.length - 1
                                    ? 'Get Started'
                                    : 'Next'}
                            </button>
                        </div>
                    </motion.div>
                )}

                {screen === 'auth' && (
                    <motion.div
                        key="auth"
                        variants={fade}
                        initial="hidden"
                        animate="show"
                        exit="exit"
                        className="w-full max-w-xs text-center"
                    >
                        <Logo />
                        <h1 className="mt-6 text-3xl font-bold text-white">
                            BiasharaMax
                        </h1>
                        <p className="mt-2 text-sm text-indigo-300">
                            Business management, simplified.
                        </p>

                        <div className="mt-10 space-y-3">
                            <a
                                href={route('login')}
                                className="block w-full rounded-xl bg-white px-6 py-3 text-sm font-semibold text-indigo-700 shadow-lg transition hover:bg-indigo-50 active:scale-95"
                            >
                                Sign In
                            </a>
                            <button
                                onClick={() => setScreen('account-type')}
                                className="block w-full rounded-xl border border-white/30 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10 active:scale-95"
                            >
                                Create Account
                            </button>
                        </div>
                    </motion.div>
                )}

                {screen === 'account-type' && (
                    <motion.div
                        key="account-type"
                        variants={fade}
                        initial="hidden"
                        animate="show"
                        exit="exit"
                        className="w-full max-w-xs"
                    >
                        <button
                            onClick={() => setScreen('auth')}
                            className="mb-6 flex items-center gap-1 text-sm text-indigo-300 hover:text-white"
                        >
                            <BackArrow /> Back
                        </button>

                        <h2 className="text-2xl font-bold text-white">
                            How would you like to start?
                        </h2>
                        <p className="mt-2 text-sm text-indigo-300">
                            Choose the option that fits you.
                        </p>

                        <div className="mt-8 space-y-4">
                            {/* Free trial card */}
                            <a
                                href={route('register')}
                                className="block rounded-2xl border border-white/20 bg-white/10 p-5 text-left backdrop-blur-sm transition hover:bg-white/20 active:scale-95"
                            >
                                <div className="flex items-start gap-4">
                                    <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/40">
                                        <RocketIcon />
                                    </span>
                                    <div>
                                        <p className="font-semibold text-white">
                                            Free Trial
                                        </p>
                                        <p className="mt-0.5 text-sm text-indigo-200">
                                            30 days free, no credit card
                                            required. Choose a plan and start
                                            right away.
                                        </p>
                                    </div>
                                </div>
                            </a>

                            {/* License code card */}
                            <button
                                onClick={() => setScreen('license')}
                                className="block w-full rounded-2xl border border-white/20 bg-white/10 p-5 text-left backdrop-blur-sm transition hover:bg-white/20 active:scale-95"
                            >
                                <div className="flex items-start gap-4">
                                    <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/40">
                                        <KeyIcon />
                                    </span>
                                    <div>
                                        <p className="font-semibold text-white">
                                            I have a License Code
                                        </p>
                                        <p className="mt-0.5 text-sm text-indigo-200">
                                            Enter your pre-purchased activation
                                            code to unlock your subscription.
                                        </p>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </motion.div>
                )}

                {screen === 'license' && (
                    <motion.div
                        key="license"
                        variants={fade}
                        initial="hidden"
                        animate="show"
                        exit="exit"
                        className="w-full max-w-xs"
                    >
                        <button
                            onClick={() => {
                                setScreen('account-type');
                                setValidatedCode(null);
                                setLicenseError('');
                            }}
                            className="mb-6 flex items-center gap-1 text-sm text-indigo-300 hover:text-white"
                        >
                            <BackArrow /> Back
                        </button>

                        <h2 className="text-2xl font-bold text-white">
                            Enter your license code
                        </h2>
                        <p className="mt-2 text-sm text-indigo-300">
                            Your code looks like:{' '}
                            <span className="font-mono">
                                XXXX-XXXX-XXXX-XXXX
                            </span>
                        </p>

                        {!validatedCode ? (
                            <div className="mt-8 space-y-4">
                                <input
                                    type="text"
                                    value={licenseCode}
                                    onChange={(e) => {
                                        setLicenseCode(
                                            e.target.value.toUpperCase(),
                                        );
                                        setLicenseError('');
                                    }}
                                    placeholder="XXXX-XXXX-XXXX-XXXX"
                                    className="block w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-center font-mono text-lg text-white backdrop-blur-sm placeholder:text-white/30 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/30"
                                    maxLength={19}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter')
                                            validateLicense();
                                    }}
                                />
                                {licenseError && (
                                    <p className="text-center text-sm text-red-300">
                                        {licenseError}
                                    </p>
                                )}
                                <button
                                    onClick={validateLicense}
                                    disabled={
                                        licenseLoading || licenseCode.length < 4
                                    }
                                    className="w-full rounded-xl bg-violet-500 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-violet-400 active:scale-95 disabled:opacity-50"
                                >
                                    {licenseLoading
                                        ? 'Validating…'
                                        : 'Validate Code'}
                                </button>
                            </div>
                        ) : (
                            <div className="mt-8 space-y-4">
                                <div className="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 p-4">
                                    <div className="flex items-center gap-2 text-emerald-300">
                                        <CheckIcon />
                                        <span className="text-sm font-semibold">
                                            Code verified!
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm font-medium text-white">
                                        {validatedCode.plan.name}
                                    </p>
                                    {validatedCode.plan.description && (
                                        <p className="mt-0.5 text-xs text-indigo-300">
                                            {validatedCode.plan.description}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-indigo-300">
                                        {validatedCode.duration_months} month
                                        {validatedCode.duration_months !== 1
                                            ? 's'
                                            : ''}{' '}
                                        subscription
                                    </p>
                                </div>
                                <button
                                    onClick={proceedWithCode}
                                    className="w-full rounded-xl bg-indigo-500 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-indigo-400 active:scale-95"
                                >
                                    Continue to Registration →
                                </button>
                                <button
                                    onClick={() => {
                                        setValidatedCode(null);
                                        setLicenseCode('');
                                    }}
                                    className="w-full text-center text-sm text-indigo-300 hover:text-white"
                                >
                                    Use a different code
                                </button>
                            </div>
                        )}
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

// ─── Splash screen ────────────────────────────────────────────────────────────

function SplashScreen() {
    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-indigo-950 via-indigo-900 to-violet-900"
        >
            <motion.div
                initial={{ scale: 0.7, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ delay: 0.2, type: 'spring', stiffness: 200 }}
                className="flex flex-col items-center gap-4"
            >
                <Logo size="lg" />
                <h1 className="text-2xl font-bold tracking-tight text-white">
                    BiasharaMax
                </h1>
                <p className="text-sm text-indigo-300">
                    Business management, simplified.
                </p>
            </motion.div>

            <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ delay: 0.8 }}
                className="absolute bottom-12 flex gap-1.5"
            >
                {[0, 1, 2].map((i) => (
                    <motion.span
                        key={i}
                        animate={{ opacity: [0.3, 1, 0.3] }}
                        transition={{
                            repeat: Infinity,
                            duration: 1.2,
                            delay: i * 0.2,
                        }}
                        className="h-1.5 w-1.5 rounded-full bg-indigo-400"
                    />
                ))}
            </motion.div>
        </motion.div>
    );
}

// ─── Small helpers ────────────────────────────────────────────────────────────

function Logo({ size = 'md' }: { size?: 'md' | 'lg' }) {
    const s = size === 'lg' ? 'h-20 w-20' : 'h-12 w-12';
    return (
        <div
            className={`${s} flex items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-xl`}
        >
            <span
                className={`font-black text-white ${size === 'lg' ? 'text-4xl' : 'text-2xl'}`}
            >
                B
            </span>
        </div>
    );
}

function BackArrow() {
    return (
        <svg
            className="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 19l-7-7 7-7"
            />
        </svg>
    );
}

function RocketIcon() {
    return (
        <svg
            className="h-5 w-5 text-indigo-200"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
            />
        </svg>
    );
}

function KeyIcon() {
    return (
        <svg
            className="h-5 w-5 text-violet-200"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
            />
        </svg>
    );
}

function CheckIcon() {
    return (
        <svg
            className="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2.5}
                d="M5 13l4 4L19 7"
            />
        </svg>
    );
}
