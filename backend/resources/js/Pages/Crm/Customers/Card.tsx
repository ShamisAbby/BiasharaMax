import Card from '@/Components/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CustomerCrmProfile } from '@/types/crm';
import { Head } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useRef } from 'react';

export default function CustomerLoyaltyCard({
    customer,
}: {
    customer: CustomerCrmProfile;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        if (!canvasRef.current) return;
        QRCode.toCanvas(
            canvasRef.current,
            route('crm.customers.show', customer.id),
            {
                width: 220,
                margin: 1,
                color: { dark: '#312e81', light: '#ffffff' },
            },
        );
    }, [customer.id]);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Digital Loyalty Card
                </h2>
            }
        >
            <Head title={`Loyalty Card — ${customer.name}`} />

            <div className="py-8">
                <div className="mx-auto max-w-md space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-purple-600 p-6 text-white shadow-lg">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-indigo-200">
                                    Loyalty Member
                                </p>
                                <h3 className="mt-1 text-lg font-semibold">
                                    {customer.name}
                                </h3>
                            </div>
                            {customer.loyalty_tier && (
                                <span className="rounded-full bg-white/15 px-3 py-1 text-xs font-medium">
                                    {customer.loyalty_tier.name}
                                </span>
                            )}
                        </div>

                        <div className="mt-6 flex items-center justify-center rounded-xl bg-white p-4">
                            <canvas ref={canvasRef} />
                        </div>

                        <div className="mt-6 flex items-center justify-between text-sm">
                            <span className="text-indigo-200">
                                Points Balance
                            </span>
                            <span className="text-lg font-bold">
                                {customer.loyalty_points}
                            </span>
                        </div>
                    </div>

                    <Card title="How to use this card">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Staff can scan this QR code to open this customer's
                            profile directly and adjust or redeem loyalty
                            points.
                        </p>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
