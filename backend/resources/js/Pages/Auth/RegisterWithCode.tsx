import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { CheckCircleIcon, KeyIcon } from '@heroicons/react/24/solid';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

interface BusinessTypeOption {
    id: string;
    name: string;
    slug: string;
}

interface CodeInfo {
    code: string;
    plan: { id: string; name: string; description: string };
    billing_cycle: string;
    duration_months: number;
}

export default function RegisterWithCode({
    businessTypes,
}: {
    businessTypes: BusinessTypeOption[];
}) {
    const [codeInfo, setCodeInfo] = useState<CodeInfo | null>(null);
    const [codeInput, setCodeInput] = useState('');
    const [codeError, setCodeError] = useState('');
    const [codeLoading, setCodeLoading] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        business_name: '',
        business_type: businessTypes[0]?.slug ?? '',
        business_phone: '',
        country: 'TZ',
        currency: 'TZS',
        owner_name: '',
        owner_email: '',
        owner_phone: '',
        password: '',
        password_confirmation: '',
        registration_code: '',
    });

    // Keep a stable ref so validateCode (called in useEffect) can access setData
    const setDataRef = useRef(setData);
    setDataRef.current = setData;

    const validateCode = async (value: string) => {
        setCodeError('');
        setCodeLoading(true);
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
                body: JSON.stringify({ code: value.trim() }),
            });
            const json = await res.json();
            if (json.valid) {
                setCodeInfo(json);
                setDataRef.current('registration_code', json.code);
            } else {
                setCodeError(json.reason ?? 'Invalid code.');
                setCodeInfo(null);
            }
        } catch {
            setCodeError('Could not validate. Please try again.');
        } finally {
            setCodeLoading(false);
        }
    };

    // Pre-fill code from URL param (?code=XXXX)
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const code = params.get('code');
        if (code) {
            const upper = code.toUpperCase();
            setCodeInput(upper);
            validateCode(upper);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const clearCode = () => {
        setCodeInfo(null);
        setCodeInput('');
        setData('registration_code', '');
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout
            maxWidth="2xl"
            panelTitle="Activate your license."
            panelDescription="Enter your license code to unlock your subscription and set up your business."
            panelHighlights={[
                'Immediate full access — no trial period',
                'All features unlocked from day one',
                'Dedicated support for licensed customers',
            ]}
        >
            <Head title="Activate License" />

            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Activate your license
                </h1>
                <p className="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
                    Enter your code first, then fill in your business details.
                </p>
            </div>

            {/* Step 1: Code validation */}
            <div
                className={`mb-8 rounded-xl border p-5 ${
                    codeInfo
                        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800/40 dark:bg-emerald-900/20'
                        : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50'
                }`}
            >
                <div className="mb-3 flex items-center gap-2">
                    <KeyIcon
                        className={`h-4 w-4 ${codeInfo ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'}`}
                    />
                    <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        License Code
                    </span>
                </div>

                {!codeInfo ? (
                    <div className="flex gap-2">
                        <TextInput
                            value={codeInput}
                            onChange={(e) => {
                                setCodeInput(e.target.value.toUpperCase());
                                setCodeError('');
                            }}
                            placeholder="XXXX-XXXX-XXXX-XXXX"
                            className="block flex-1 font-mono"
                            maxLength={19}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    validateCode(codeInput);
                                }
                            }}
                        />
                        <PrimaryButton
                            type="button"
                            disabled={codeLoading || codeInput.length < 4}
                            onClick={() => validateCode(codeInput)}
                            className="shrink-0"
                        >
                            {codeLoading ? 'Checking…' : 'Validate'}
                        </PrimaryButton>
                    </div>
                ) : (
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400">
                                <CheckCircleIcon className="h-4 w-4" />
                                <span className="text-sm font-semibold">
                                    Verified: {codeInfo.code}
                                </span>
                            </div>
                            <p className="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">
                                {codeInfo.plan.name}
                            </p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                {codeInfo.duration_months} month
                                {codeInfo.duration_months !== 1
                                    ? 's'
                                    : ''} · {codeInfo.billing_cycle}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={clearCode}
                            className="text-xs text-gray-400 hover:text-red-500"
                        >
                            Change
                        </button>
                    </div>
                )}

                {codeError && (
                    <p className="mt-2 text-sm text-red-600 dark:text-red-400">
                        {codeError}
                    </p>
                )}
                {errors.registration_code && (
                    <p className="mt-2 text-sm text-red-600">
                        {errors.registration_code}
                    </p>
                )}
            </div>

            {/* Step 2: Registration form — shown after code is validated */}
            {codeInfo && (
                <form onSubmit={submit} className="space-y-8">
                    <fieldset className="space-y-4">
                        <legend className="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Business details
                        </legend>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel
                                    htmlFor="business_name"
                                    value="Business name"
                                />
                                <TextInput
                                    id="business_name"
                                    className="mt-1.5 block w-full"
                                    isFocused
                                    value={data.business_name}
                                    onChange={(e) =>
                                        setData('business_name', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.business_name}
                                    className="mt-2"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="business_type"
                                    value="Business type"
                                />
                                <SelectInput
                                    id="business_type"
                                    className="mt-1.5 block w-full"
                                    value={data.business_type}
                                    onChange={(e) =>
                                        setData('business_type', e.target.value)
                                    }
                                    required
                                >
                                    {businessTypes.map((t) => (
                                        <option key={t.id} value={t.slug}>
                                            {t.name}
                                        </option>
                                    ))}
                                </SelectInput>
                                <InputError
                                    message={errors.business_type}
                                    className="mt-2"
                                />
                            </div>
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="business_phone"
                                value="Business phone (optional)"
                            />
                            <TextInput
                                id="business_phone"
                                className="mt-1.5 block w-full"
                                value={data.business_phone}
                                onChange={(e) =>
                                    setData('business_phone', e.target.value)
                                }
                            />
                        </div>
                    </fieldset>

                    <fieldset className="space-y-4 border-t border-gray-100 pt-8 dark:border-gray-700">
                        <legend className="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Your account
                        </legend>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel
                                    htmlFor="owner_name"
                                    value="Your name"
                                />
                                <TextInput
                                    id="owner_name"
                                    className="mt-1.5 block w-full"
                                    value={data.owner_name}
                                    onChange={(e) =>
                                        setData('owner_name', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.owner_name}
                                    className="mt-2"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="owner_email"
                                    value="Email"
                                />
                                <TextInput
                                    id="owner_email"
                                    type="email"
                                    className="mt-1.5 block w-full"
                                    value={data.owner_email}
                                    onChange={(e) =>
                                        setData('owner_email', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.owner_email}
                                    className="mt-2"
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel
                                    htmlFor="password"
                                    value="Password"
                                />
                                <PasswordInput
                                    id="password"
                                    className="mt-1.5 block w-full"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.password}
                                    className="mt-2"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="password_confirmation"
                                    value="Confirm password"
                                />
                                <PasswordInput
                                    id="password_confirmation"
                                    className="mt-1.5 block w-full"
                                    value={data.password_confirmation}
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                        </div>
                    </fieldset>

                    <div className="flex items-center justify-between border-t border-gray-100 pt-6 dark:border-gray-700">
                        <Link
                            href={route('login')}
                            className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            Already registered?{' '}
                            <span className="font-medium text-indigo-600 dark:text-indigo-400">
                                Sign in
                            </span>
                        </Link>
                        <PrimaryButton
                            className="px-6 py-2.5"
                            disabled={processing}
                        >
                            Activate &amp; Continue
                        </PrimaryButton>
                    </div>
                </form>
            )}

            {!codeInfo && (
                <p className="mt-2 text-center text-sm text-gray-500 dark:text-gray-400">
                    Don&apos;t have a license code?{' '}
                    <Link
                        href={route('register')}
                        className="font-medium text-indigo-600 dark:text-indigo-400"
                    >
                        Start a free trial instead
                    </Link>
                </p>
            )}
        </GuestLayout>
    );
}
