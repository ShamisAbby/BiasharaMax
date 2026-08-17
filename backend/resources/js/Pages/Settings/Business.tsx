import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Business } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const BUSINESS_TYPES = [
    'retail',
    'supermarket',
    'restaurant',
    'pharmacy',
    'hardware',
    'electronics',
    'fashion',
    'beauty',
    'wholesale',
    'service',
    'other',
];

export default function BusinessSettings({ business }: { business: Business }) {
    const { data, setData, patch, processing, errors, recentlySuccessful } =
        useForm({
            name: business.name,
            business_type: business.business_type,
            phone: business.phone ?? '',
            address: business.address ?? '',
            city: business.city ?? '',
            country: business.country,
            currency: business.currency,
            timezone: business.timezone,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('settings.business.update'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Business Settings
                </h2>
            }
        >
            <Head title="Business Settings" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card
                        title="Business profile"
                        description="Update your business information."
                    >
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <InputLabel
                                    htmlFor="name"
                                    value="Business name"
                                />
                                <TextInput
                                    id="name"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.name}
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
                                    className="mt-1 block w-full capitalize"
                                    value={data.business_type}
                                    onChange={(e) =>
                                        setData('business_type', e.target.value)
                                    }
                                >
                                    {BUSINESS_TYPES.map((type) => (
                                        <option
                                            key={type}
                                            value={type}
                                            className="capitalize"
                                        >
                                            {type}
                                        </option>
                                    ))}
                                </SelectInput>
                                <InputError
                                    message={errors.business_type}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="phone" value="Phone" />
                                    <TextInput
                                        id="phone"
                                        className="mt-1 block w-full"
                                        value={data.phone}
                                        onChange={(e) =>
                                            setData('phone', e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={errors.phone}
                                        className="mt-2"
                                    />
                                </div>
                                <div>
                                    <InputLabel htmlFor="city" value="City" />
                                    <TextInput
                                        id="city"
                                        className="mt-1 block w-full"
                                        value={data.city}
                                        onChange={(e) =>
                                            setData('city', e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={errors.city}
                                        className="mt-2"
                                    />
                                </div>
                            </div>

                            <div>
                                <InputLabel htmlFor="address" value="Address" />
                                <TextInput
                                    id="address"
                                    className="mt-1 block w-full"
                                    value={data.address}
                                    onChange={(e) =>
                                        setData('address', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.address}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <InputLabel
                                        htmlFor="country"
                                        value="Country code"
                                    />
                                    <TextInput
                                        id="country"
                                        className="mt-1 block w-full uppercase"
                                        value={data.country}
                                        maxLength={2}
                                        onChange={(e) =>
                                            setData(
                                                'country',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.country}
                                        className="mt-2"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        htmlFor="currency"
                                        value="Currency"
                                    />
                                    <TextInput
                                        id="currency"
                                        className="mt-1 block w-full uppercase"
                                        value={data.currency}
                                        maxLength={3}
                                        onChange={(e) =>
                                            setData(
                                                'currency',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.currency}
                                        className="mt-2"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        htmlFor="timezone"
                                        value="Timezone"
                                    />
                                    <TextInput
                                        id="timezone"
                                        className="mt-1 block w-full"
                                        value={data.timezone}
                                        onChange={(e) =>
                                            setData('timezone', e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={errors.timezone}
                                        className="mt-2"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <PrimaryButton disabled={processing}>
                                    Save
                                </PrimaryButton>
                                {recentlySuccessful && (
                                    <p className="text-sm text-gray-500">
                                        Saved.
                                    </p>
                                )}
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
