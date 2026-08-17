import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import { useConfirm } from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatCard from '@/Components/StatCard';
import TextInput from '@/Components/TextInput';
import WebsiteLayout from '@/Layouts/WebsiteLayout';
import { formatCurrency } from '@/lib/currency';
import { BusinessWebsite } from '@/types/website';
import {
    ChatBubbleLeftRightIcon,
    DocumentTextIcon,
    GlobeAltIcon,
    PaintBrushIcon,
    ShieldCheckIcon,
    ShoppingCartIcon,
} from '@heroicons/react/24/outline';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface WebsiteDashboardSummary {
    online_orders_this_month: number;
    online_revenue_this_month: number;
    online_orders_total: number;
    open_enquiries_count: number;
}

interface RecentOrder {
    sale_number: string;
    customer_name: string;
    total_amount: string;
    created_at: string;
}

export default function WebsiteDashboard({
    website,
    subdomainUrl,
    summary,
    recentOrders,
}: {
    website: BusinessWebsite;
    subdomainUrl: string;
    summary: WebsiteDashboardSummary;
    recentOrders: RecentOrder[];
}) {
    const confirm = useConfirm();

    const seoForm = useForm({
        seo_title: website.seo_title ?? '',
        seo_description: website.seo_description ?? '',
    });

    const publish = () =>
        confirm({
            title: 'Publish this website?',
            message: 'It becomes visible to anyone with the link immediately.',
            confirmLabel: 'Publish',
            tone: 'info',
            onConfirm: () => router.post(route('website.publish', website.id)),
        });

    const unpublish = () =>
        confirm({
            title: 'Unpublish this website?',
            message:
                'Your site, shop and blog all go offline \u2014 visitors get a short "temporarily unavailable" page with your contact details. Your content is kept and can be republished at any time.',
            confirmLabel: 'Unpublish',
            tone: 'warning',
            onConfirm: () =>
                router.post(route('website.unpublish', website.id)),
        });

    const submitSeo = (e: FormEvent) => {
        e.preventDefault();
        seoForm.patch(route('website.update', website.id), {
            preserveScroll: true,
        });
    };

    return (
        <WebsiteLayout title="Dashboard">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Your Website
                    </h3>
                    <a
                        href={subdomainUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="text-sm text-indigo-600 hover:underline"
                    >
                        {subdomainUrl}
                    </a>
                </div>
                {website.status === 'draft' ? (
                    <PrimaryButton onClick={publish}>
                        Publish Website
                    </PrimaryButton>
                ) : (
                    <SecondaryButton onClick={unpublish}>
                        Unpublish
                    </SecondaryButton>
                )}
            </div>

            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                {/*
                  Three states, not two. "Draft" alone was misleading: a
                  site that has never been published still shows the
                  business type's template publicly, whereas one that was
                  taken down is genuinely offline. Saying which is which
                  saves an owner checking the storefront to find out.
                */}
                <StatCard
                    icon={<GlobeAltIcon className="h-5 w-5" />}
                    iconClassName={
                        website.status === 'published'
                            ? 'bg-emerald-600'
                            : website.published_at
                              ? 'bg-red-600'
                              : 'bg-gray-500'
                    }
                    title="Website Status"
                    value={
                        website.status === 'published'
                            ? 'Published'
                            : website.published_at
                              ? 'Offline'
                              : 'Draft'
                    }
                    footer={
                        website.status === 'published'
                            ? 'Visible to anyone with the link'
                            : website.published_at
                              ? 'Taken offline — visitors see a 404'
                              : 'Not published yet — visitors see your business type\u2019s template'
                    }
                />
                <StatCard
                    icon={<ShieldCheckIcon className="h-5 w-5" />}
                    iconClassName="bg-blue-600"
                    title="SSL Status"
                    value="Secured"
                    delta="Shared subdomain HTTPS"
                />
                <StatCard
                    icon={<PaintBrushIcon className="h-5 w-5" />}
                    iconClassName="bg-purple-600"
                    title="Template"
                    value={website.template_name ?? 'None assigned'}
                />
                <StatCard
                    icon={<DocumentTextIcon className="h-5 w-5" />}
                    iconClassName="bg-amber-600"
                    title="Pages"
                    value={website.pages.length}
                    delta={`${website.pages.filter((p) => p.is_enabled).length} enabled`}
                />
            </div>

            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={<ShoppingCartIcon className="h-5 w-5" />}
                    iconClassName="bg-indigo-600"
                    title="Online Orders"
                    value={summary.online_orders_this_month}
                    delta="This month"
                />
                <StatCard
                    icon={<ShoppingCartIcon className="h-5 w-5" />}
                    iconClassName="bg-emerald-600"
                    title="Revenue from Website"
                    value={formatCurrency(summary.online_revenue_this_month)}
                    delta="This month"
                />
                <StatCard
                    icon={<ShoppingCartIcon className="h-5 w-5" />}
                    iconClassName="bg-blue-600"
                    title="Total Online Orders"
                    value={summary.online_orders_total}
                />
                <StatCard
                    icon={<ChatBubbleLeftRightIcon className="h-5 w-5" />}
                    iconClassName="bg-rose-600"
                    title="Open Enquiries"
                    value={summary.open_enquiries_count}
                    href={route('website.enquiries.index')}
                    delta="View enquiries"
                    deltaTone={
                        summary.open_enquiries_count > 0
                            ? 'warning'
                            : 'positive'
                    }
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card
                    title="Pages"
                    description="Edit content and SEO fields for each page"
                    actions={
                        <Link
                            href={route('website.pages')}
                            className="text-sm font-medium text-indigo-600 hover:underline"
                        >
                            Manage Pages
                        </Link>
                    }
                >
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {website.pages.map((page) => (
                            <div
                                key={page.id}
                                className="flex items-center justify-between py-2.5 text-sm"
                            >
                                <span className="text-gray-900 dark:text-gray-100">
                                    {page.title}
                                </span>
                                <Badge
                                    variant={
                                        page.is_enabled ? 'success' : 'neutral'
                                    }
                                >
                                    {page.is_enabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                        ))}
                        {website.pages.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No pages yet — assign a website template to your
                                business type to get started.
                            </p>
                        )}
                    </div>
                </Card>

                <Card title="Recent Online Orders">
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {recentOrders.map((order) => (
                            <div
                                key={order.sale_number}
                                className="flex items-center justify-between py-2.5 text-sm"
                            >
                                <div>
                                    <Link
                                        href={route('sales.orders.index')}
                                        className="font-medium text-indigo-600 hover:underline"
                                    >
                                        {order.sale_number}
                                    </Link>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {order.customer_name}
                                    </p>
                                </div>
                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                    {formatCurrency(order.total_amount)}
                                </span>
                            </div>
                        ))}
                        {recentOrders.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No online orders yet.
                            </p>
                        )}
                    </div>
                </Card>
            </div>

            <Card
                title="Site SEO"
                description="Shown in search engine results and social previews"
            >
                <form onSubmit={submitSeo} className="space-y-4">
                    <div>
                        <TextInput
                            placeholder="SEO title"
                            className="block w-full"
                            value={seoForm.data.seo_title}
                            onChange={(e) =>
                                seoForm.setData('seo_title', e.target.value)
                            }
                        />
                        <InputError
                            message={seoForm.errors.seo_title}
                            className="mt-2"
                        />
                    </div>
                    <textarea
                        placeholder="SEO meta description"
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        rows={2}
                        value={seoForm.data.seo_description}
                        onChange={(e) =>
                            seoForm.setData('seo_description', e.target.value)
                        }
                    />
                    <div className="flex justify-end">
                        <PrimaryButton
                            type="submit"
                            disabled={seoForm.processing}
                        >
                            Save SEO Settings
                        </PrimaryButton>
                    </div>
                </form>
            </Card>
        </WebsiteLayout>
    );
}
