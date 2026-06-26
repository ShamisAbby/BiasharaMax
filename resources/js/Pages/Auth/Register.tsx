import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { formatCurrency } from '@/lib/currency';
import { SubscriptionPlan } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const BUSINESS_TYPES = [
    { value: 'retail', label: 'Retail Shop' },
    { value: 'supermarket', label: 'Supermarket' },
    { value: 'restaurant', label: 'Restaurant' },
    { value: 'pharmacy', label: 'Pharmacy' },
    { value: 'hardware', label: 'Hardware Store' },
    { value: 'electronics', label: 'Electronics Shop' },
    { value: 'fashion', label: 'Fashion Store' },
    { value: 'beauty', label: 'Beauty Salon' },
    { value: 'wholesale', label: 'Wholesale Business' },
    { value: 'service', label: 'Service Business' },
    { value: 'other', label: 'Other' },
];

export default function Register({ plans }: { plans: SubscriptionPlan[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        business_name: '',
        business_type: 'retail',
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
        <GuestLayout>
            <Head title="Start your free trial" />

            <div className="mb-6 text-center">
                <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                    Set up your business
                </h1>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    30 days free. No credit card required.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-6">
                <fieldset className="space-y-4">
                    <legend className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Business details
                    </legend>

                    <div>
                        <InputLabel
                            htmlFor="business_name"
                            value="Business name"
                        />
                        <TextInput
                            id="business_name"
                            name="business_name"
                            value={data.business_name}
                            className="mt-1 block w-full"
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
                            className="mt-1 block w-full"
                            onChange={(e) =>
                                setData('business_type', e.target.value)
                            }
                            required
                        >
                            {BUSINESS_TYPES.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </SelectInput>
                        <InputError
                            message={errors.business_type}
                            className="mt-2"
                        />
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
                            className="mt-1 block w-full"
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

                <fieldset className="space-y-4 border-t border-gray-100 pt-6 dark:border-gray-700">
                    <legend className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Your account
                    </legend>

                    <div>
                        <InputLabel htmlFor="owner_name" value="Your name" />
                        <TextInput
                            id="owner_name"
                            name="owner_name"
                            value={data.owner_name}
                            className="mt-1 block w-full"
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
                            className="mt-1 block w-full"
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

                    <div>
                        <InputLabel
                            htmlFor="owner_phone"
                            value="Phone (optional)"
                        />
                        <TextInput
                            id="owner_phone"
                            name="owner_phone"
                            value={data.owner_phone}
                            className="mt-1 block w-full"
                            onChange={(e) =>
                                setData('owner_phone', e.target.value)
                            }
                        />
                        <InputError
                            message={errors.owner_phone}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="password" value="Password" />
                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full"
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
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={errors.password_confirmation}
                            className="mt-2"
                        />
                    </div>
                </fieldset>

                <fieldset className="space-y-3 border-t border-gray-100 pt-6 dark:border-gray-700">
                    <legend className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Choose a plan
                    </legend>

                    <div className="grid gap-3 sm:grid-cols-3">
                        {plans.map((plan) => (
                            <label
                                key={plan.id}
                                className={`cursor-pointer rounded-lg border p-4 text-sm transition ${
                                    data.subscription_plan_id === plan.id
                                        ? 'border-indigo-500 ring-2 ring-indigo-500'
                                        : 'border-gray-200 dark:border-gray-700'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="subscription_plan_id"
                                    value={plan.id}
                                    checked={
                                        data.subscription_plan_id === plan.id
                                    }
                                    onChange={() =>
                                        setData('subscription_plan_id', plan.id)
                                    }
                                    className="sr-only"
                                />
                                <div className="font-semibold text-gray-900 dark:text-gray-100">
                                    {plan.name}
                                </div>
                                <div className="mt-1 text-lg font-bold text-indigo-600">
                                    TZS {formatCurrency(plan.price_monthly)}
                                    <span className="text-xs font-normal text-gray-500">
                                        {' '}
                                        /mo
                                    </span>
                                </div>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {plan.description}
                                </p>
                            </label>
                        ))}
                    </div>
                    <InputError
                        message={errors.subscription_plan_id}
                        className="mt-2"
                    />
                </fieldset>

                <div className="flex items-center justify-end">
                    <Link
                        href={route('login')}
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                    >
                        Already registered?
                    </Link>

                    <PrimaryButton className="ms-4" disabled={processing}>
                        Start free trial
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
