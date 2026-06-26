import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Subscription, SubscriptionPlan } from '@/types';
import { Head } from '@inertiajs/react';

export default function SubscriptionShow({
    subscription,
    plans,
}: {
    subscription: Subscription | null;
    plans: SubscriptionPlan[];
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Subscription
                </h2>
            }
        >
            <Head title="Subscription" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <Card title="Current plan">
                        {subscription ? (
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {subscription.plan?.name}
                                    </p>
                                    {subscription.status === 'trialing' &&
                                        subscription.trial_ends_at && (
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                Trial ends{' '}
                                                {new Date(
                                                    subscription.trial_ends_at,
                                                ).toLocaleDateString()}
                                            </p>
                                        )}
                                </div>
                                <Badge variant={subscription.status === 'trialing' ? 'info' : 'success'}>
                                    {subscription.status}
                                </Badge>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No active subscription.</p>
                        )}
                    </Card>

                    <Card title="Available plans" description="Upgrades and billing are managed by our team during early access.">
                        <div className="grid gap-4 sm:grid-cols-3">
                            {plans.map((plan) => (
                                <div
                                    key={plan.id}
                                    className={`rounded-lg border p-4 ${
                                        subscription?.plan?.id === plan.id
                                            ? 'border-indigo-500 ring-2 ring-indigo-500'
                                            : 'border-gray-200 dark:border-gray-700'
                                    }`}
                                >
                                    <p className="font-semibold text-gray-900 dark:text-gray-100">
                                        {plan.name}
                                    </p>
                                    <p className="mt-1 text-lg font-bold text-indigo-600">
                                        KES {plan.price_monthly}
                                        <span className="text-xs font-normal text-gray-500">
                                            {' '}
                                            /mo
                                        </span>
                                    </p>
                                    <ul className="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                        {plan.features.map((feature) => (
                                            <li key={feature}>&bull; {feature}</li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
