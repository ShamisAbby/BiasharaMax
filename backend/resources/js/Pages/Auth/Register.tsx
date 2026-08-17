import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { formatCurrency } from '@/lib/currency';
import { SubscriptionPlan } from '@/types';
import { CheckCircleIcon } from '@heroicons/react/24/solid';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface BusinessTypeOption {
    id: string;
    name: string;
    slug: string;
}

export default function Register({
    plans,
    businessTypes,
}: {
    plans: SubscriptionPlan[];
    businessTypes: BusinessTypeOption[];
}) {
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
        subscription_plan_id: plans[0]?.id ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout
            maxWidth="2xl"
            panelTitle="Start your free trial."
            panelDescription="30 days free, no credit card required. Set up your business and start selling in minutes."
            panelHighlights={[
                'Real-time inventory across every branch',
                'Sales, payments, and reporting in one place',
                'Cancel anytime, no questions asked',
            ]}
        >
            <Head title="Start your free trial" />

            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Set up your business
                </h1>
                <p className="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
                    30 days free. No credit card required.
                </p>
            </div>

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
                                name="business_name"
                                value={data.business_name}
                                className="mt-1.5 block w-full"
                                isFocused
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
                                name="business_type"
                                value={data.business_type}
                                className="mt-1.5 block w-full"
                                onChange={(e) =>
                                    setData('business_type', e.target.value)
                                }
                                required
                            >
                                {businessTypes.map((type) => (
                                    <option key={type.id} value={type.slug}>
                                        {type.name}
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
                            name="business_phone"
                            value={data.business_phone}
                            className="mt-1.5 block w-full"
                            onChange={(e) =>
                                setData('business_phone', e.target.value)
                            }
                        />
                        <InputError
                            message={errors.business_phone}
                            className="mt-2"
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
                                name="owner_name"
                                value={data.owner_name}
                                className="mt-1.5 block w-full"
                                autoComplete="name"
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
                            <InputLabel htmlFor="owner_email" value="Email" />
                            <TextInput
                                id="owner_email"
                                type="email"
                                name="owner_email"
                                value={data.owner_email}
                                className="mt-1.5 block w-full"
                                autoComplete="username"
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

                    <div>
                        <InputLabel
                            htmlFor="owner_phone"
                            value="Phone (optional)"
                        />
                        <TextInput
                            id="owner_phone"
                            name="owner_phone"
                            value={data.owner_phone}
                            className="mt-1.5 block w-full"
                            onChange={(e) =>
                                setData('owner_phone', e.target.value)
                            }
                        />
                        <InputError
                            message={errors.owner_phone}
                            className="mt-2"
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="password" value="Password" />
                            <PasswordInput
                                id="password"
                                name="password"
                                value={data.password}
                                className="mt-1.5 block w-full"
                                autoComplete="new-password"
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
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="mt-1.5 block w-full"
                                autoComplete="new-password"
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={errors.password_confirmation}
                                className="mt-2"
                            />
                        </div>
                    </div>
                </fieldset>

                <fieldset className="space-y-4 border-t border-gray-100 pt-8 dark:border-gray-700">
                    <legend className="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        Choose a plan
                    </legend>

                    <div className="grid gap-3 sm:grid-cols-3">
                        {plans.map((plan) => {
                            const selected =
                                data.subscription_plan_id === plan.id;

                            return (
                                <label
                                    key={plan.id}
                                    className={`relative cursor-pointer rounded-xl border p-4 text-sm transition ${
                                        selected
                                            ? 'border-indigo-500 bg-indigo-50/60 ring-1 ring-indigo-500 dark:bg-indigo-900/20'
                                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="subscription_plan_id"
                                        value={plan.id}
                                        checked={selected}
                                        onChange={() =>
                                            setData(
                                                'subscription_plan_id',
                                                plan.id,
                                            )
                                        }
                                        className="sr-only"
                                    />
                                    {selected && (
                                        <CheckCircleIcon className="absolute right-3 top-3 h-5 w-5 text-indigo-500" />
                                    )}
                                    <div className="font-semibold text-gray-900 dark:text-gray-100">
                                        {plan.name}
                                    </div>
                                    <div className="mt-1 text-lg font-bold text-indigo-600 dark:text-indigo-400">
                                        TZS {formatCurrency(plan.price_monthly)}
                                        <span className="text-xs font-normal text-gray-500 dark:text-gray-400">
                                            {' '}
                                            /mo
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {plan.description}
                                    </p>
                                </label>
                            );
                        })}
                    </div>
                    <InputError
                        message={errors.subscription_plan_id}
                        className="mt-2"
                    />
                </fieldset>

                <div className="flex items-center justify-between border-t border-gray-100 pt-6 dark:border-gray-700">
                    <Link
                        href={route('login')}
                        className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        Already registered?{' '}
                        <span className="font-medium text-indigo-600 dark:text-indigo-400">
                            Log in
                        </span>
                    </Link>

                    <PrimaryButton
                        className="px-6 py-2.5"
                        disabled={processing}
                    >
                        Start free trial
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
