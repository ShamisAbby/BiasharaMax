import Badge from '@/Components/Badge';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import WebsiteLayout from '@/Layouts/WebsiteLayout';
import { BusinessWebsite, BusinessWebsitePage } from '@/types/website';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface FeatureItem {
    title: string;
    description: string;
}
interface NamedItem {
    name: string;
    description: string;
}
interface QuoteItem {
    quote: string;
    author: string;
}
interface FaqItem {
    question: string;
    answer: string;
}

interface PageFormData {
    title: string;
    heading: string;
    body: string;
    intro: string;
    eyebrow: string;
    headline: string;
    subheadline: string;
    primary_cta: string;
    secondary_cta: string;
    features: FeatureItem[];
    items: NamedItem[];
    testimonialItems: QuoteItem[];
    faqItems: FaqItem[];
    seo_title: string;
    seo_description: string;
    is_enabled: boolean;
}

function toFormData(page: BusinessWebsitePage): PageFormData {
    const content = page.content ?? {};
    const hero = (content.hero ?? {}) as Record<string, string>;

    return {
        title: page.title,
        heading: (content.heading as string) ?? '',
        body: (content.body as string) ?? '',
        intro: (content.intro as string) ?? '',
        eyebrow: hero.eyebrow ?? '',
        headline: hero.headline ?? '',
        subheadline: hero.subheadline ?? '',
        primary_cta: hero.primary_cta ?? '',
        secondary_cta: hero.secondary_cta ?? '',
        features: (content.features as FeatureItem[]) ?? [],
        items: (content.items as NamedItem[]) ?? [],
        testimonialItems: (content.items as QuoteItem[]) ?? [],
        faqItems: (content.items as FaqItem[]) ?? [],
        seo_title: page.seo_title ?? '',
        seo_description: page.seo_description ?? '',
        is_enabled: page.is_enabled,
    };
}

function toContent(type: string, data: PageFormData): Record<string, unknown> {
    switch (type) {
        case 'homepage':
            return {
                hero: {
                    eyebrow: data.eyebrow,
                    headline: data.headline,
                    subheadline: data.subheadline,
                    primary_cta: data.primary_cta,
                    secondary_cta: data.secondary_cta,
                },
                features: data.features,
            };
        case 'about':
            return { heading: data.heading, body: data.body };
        case 'products':
        case 'categories':
        case 'services':
            return {
                heading: data.heading,
                intro: data.intro,
                items: data.items,
            };
        case 'gallery':
        case 'booking_form':
        case 'contact':
            return { heading: data.heading, intro: data.intro };
        case 'testimonials':
            return { heading: data.heading, items: data.testimonialItems };
        case 'faq':
            return { heading: data.heading, items: data.faqItems };
        default:
            return {
                heading: data.heading,
                intro: data.intro,
                body: data.body,
            };
    }
}

export default function WebsitePages({
    website,
}: {
    website: BusinessWebsite;
}) {
    const [editing, setEditing] = useState<BusinessWebsitePage | null>(null);

    return (
        <WebsiteLayout title="Pages">
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                Manage Pages
            </h3>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            {['Page', 'Type', 'Status', ''].map((h) => (
                                <th
                                    key={h}
                                    className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {website.pages.map((page) => (
                            <tr
                                key={page.id}
                                className="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                            >
                                <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {page.title}
                                </td>
                                <td className="px-4 py-3 text-sm capitalize text-gray-500 dark:text-gray-400">
                                    {page.type.replace('_', ' ')}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Badge
                                        variant={
                                            page.is_enabled
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {page.is_enabled
                                            ? 'Enabled'
                                            : 'Disabled'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <button
                                        onClick={() => setEditing(page)}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {website.pages.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No pages yet — assign a website template to your
                        business type to get started.
                    </p>
                )}
            </div>

            {editing && (
                <PageEditor
                    website={website}
                    page={editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </WebsiteLayout>
    );
}

function PageEditor({
    website,
    page,
    onClose,
}: {
    website: BusinessWebsite;
    page: BusinessWebsitePage;
    onClose: () => void;
}) {
    const form = useForm<PageFormData>(toFormData(page));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            title: data.title,
            content: toContent(page.type, data),
            seo_title: data.seo_title,
            seo_description: data.seo_description,
            is_enabled: data.is_enabled,
        }));
        form.patch(route('website.pages.update', [website.id, page.id]), {
            onSuccess: onClose,
        });
    };

    const updateFeature = (
        index: number,
        key: keyof FeatureItem,
        value: string,
    ) => {
        const next = [...form.data.features];
        next[index] = { ...next[index], [key]: value };
        form.setData('features', next);
    };

    const updateItem = (index: number, key: keyof NamedItem, value: string) => {
        const next = [...form.data.items];
        next[index] = { ...next[index], [key]: value };
        form.setData('items', next);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8">
            <div className="w-full max-w-2xl rounded-xl bg-white shadow-xl dark:bg-gray-800">
                <form onSubmit={submit} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Edit {page.title}
                    </h2>

                    <div className="mt-4 space-y-4">
                        <div>
                            <TextInput
                                placeholder="Page title"
                                className="block w-full"
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                            />
                            <InputError
                                message={form.errors.title}
                                className="mt-2"
                            />
                        </div>

                        {page.type === 'homepage' && (
                            <>
                                <TextInput
                                    placeholder="Eyebrow text"
                                    className="block w-full"
                                    value={form.data.eyebrow}
                                    onChange={(e) =>
                                        form.setData('eyebrow', e.target.value)
                                    }
                                />
                                <TextInput
                                    placeholder="Headline"
                                    className="block w-full"
                                    value={form.data.headline}
                                    onChange={(e) =>
                                        form.setData('headline', e.target.value)
                                    }
                                />
                                <textarea
                                    placeholder="Subheadline"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    rows={2}
                                    value={form.data.subheadline}
                                    onChange={(e) =>
                                        form.setData(
                                            'subheadline',
                                            e.target.value,
                                        )
                                    }
                                />
                                <div className="grid grid-cols-2 gap-3">
                                    <TextInput
                                        placeholder="Primary CTA"
                                        value={form.data.primary_cta}
                                        onChange={(e) =>
                                            form.setData(
                                                'primary_cta',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <TextInput
                                        placeholder="Secondary CTA"
                                        value={form.data.secondary_cta}
                                        onChange={(e) =>
                                            form.setData(
                                                'secondary_cta',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Features
                                </p>
                                {form.data.features.map((feature, index) => (
                                    <div
                                        key={index}
                                        className="grid grid-cols-2 gap-3"
                                    >
                                        <TextInput
                                            placeholder="Title"
                                            value={feature.title}
                                            onChange={(e) =>
                                                updateFeature(
                                                    index,
                                                    'title',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <TextInput
                                            placeholder="Description"
                                            value={feature.description}
                                            onChange={(e) =>
                                                updateFeature(
                                                    index,
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                            </>
                        )}

                        {page.type === 'about' && (
                            <>
                                <TextInput
                                    placeholder="Heading"
                                    className="block w-full"
                                    value={form.data.heading}
                                    onChange={(e) =>
                                        form.setData('heading', e.target.value)
                                    }
                                />
                                <textarea
                                    placeholder="Body"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    rows={4}
                                    value={form.data.body}
                                    onChange={(e) =>
                                        form.setData('body', e.target.value)
                                    }
                                />
                            </>
                        )}

                        {['products', 'categories', 'services'].includes(
                            page.type,
                        ) && (
                            <>
                                <TextInput
                                    placeholder="Heading"
                                    className="block w-full"
                                    value={form.data.heading}
                                    onChange={(e) =>
                                        form.setData('heading', e.target.value)
                                    }
                                />
                                <textarea
                                    placeholder="Intro"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    rows={2}
                                    value={form.data.intro}
                                    onChange={(e) =>
                                        form.setData('intro', e.target.value)
                                    }
                                />
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Items
                                </p>
                                {form.data.items.map((item, index) => (
                                    <div
                                        key={index}
                                        className="grid grid-cols-2 gap-3"
                                    >
                                        <TextInput
                                            placeholder="Name"
                                            value={item.name}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <TextInput
                                            placeholder="Description"
                                            value={item.description}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                            </>
                        )}

                        {['gallery', 'booking_form', 'contact'].includes(
                            page.type,
                        ) && (
                            <>
                                <TextInput
                                    placeholder="Heading"
                                    className="block w-full"
                                    value={form.data.heading}
                                    onChange={(e) =>
                                        form.setData('heading', e.target.value)
                                    }
                                />
                                <textarea
                                    placeholder="Intro"
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    rows={2}
                                    value={form.data.intro}
                                    onChange={(e) =>
                                        form.setData('intro', e.target.value)
                                    }
                                />
                            </>
                        )}

                        {page.type === 'testimonials' && (
                            <>
                                <TextInput
                                    placeholder="Heading"
                                    className="block w-full"
                                    value={form.data.heading}
                                    onChange={(e) =>
                                        form.setData('heading', e.target.value)
                                    }
                                />
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Testimonials
                                </p>
                                {form.data.testimonialItems.map(
                                    (item, index) => (
                                        <div
                                            key={index}
                                            className="grid grid-cols-2 gap-3"
                                        >
                                            <TextInput
                                                placeholder="Quote"
                                                value={item.quote}
                                                onChange={(e) => {
                                                    const next = [
                                                        ...form.data
                                                            .testimonialItems,
                                                    ];
                                                    next[index] = {
                                                        ...next[index],
                                                        quote: e.target.value,
                                                    };
                                                    form.setData(
                                                        'testimonialItems',
                                                        next,
                                                    );
                                                }}
                                            />
                                            <TextInput
                                                placeholder="Author"
                                                value={item.author}
                                                onChange={(e) => {
                                                    const next = [
                                                        ...form.data
                                                            .testimonialItems,
                                                    ];
                                                    next[index] = {
                                                        ...next[index],
                                                        author: e.target.value,
                                                    };
                                                    form.setData(
                                                        'testimonialItems',
                                                        next,
                                                    );
                                                }}
                                            />
                                        </div>
                                    ),
                                )}
                            </>
                        )}

                        {page.type === 'faq' && (
                            <>
                                <TextInput
                                    placeholder="Heading"
                                    className="block w-full"
                                    value={form.data.heading}
                                    onChange={(e) =>
                                        form.setData('heading', e.target.value)
                                    }
                                />
                                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Questions
                                </p>
                                {form.data.faqItems.map((item, index) => (
                                    <div key={index} className="space-y-2">
                                        <TextInput
                                            placeholder="Question"
                                            className="block w-full"
                                            value={item.question}
                                            onChange={(e) => {
                                                const next = [
                                                    ...form.data.faqItems,
                                                ];
                                                next[index] = {
                                                    ...next[index],
                                                    question: e.target.value,
                                                };
                                                form.setData('faqItems', next);
                                            }}
                                        />
                                        <textarea
                                            placeholder="Answer"
                                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            rows={2}
                                            value={item.answer}
                                            onChange={(e) => {
                                                const next = [
                                                    ...form.data.faqItems,
                                                ];
                                                next[index] = {
                                                    ...next[index],
                                                    answer: e.target.value,
                                                };
                                                form.setData('faqItems', next);
                                            }}
                                        />
                                    </div>
                                ))}
                            </>
                        )}

                        <div className="border-t border-gray-100 pt-4 dark:border-gray-700">
                            <p className="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                SEO
                            </p>
                            <TextInput
                                placeholder="SEO title"
                                className="block w-full"
                                value={form.data.seo_title}
                                onChange={(e) =>
                                    form.setData('seo_title', e.target.value)
                                }
                            />
                            <textarea
                                placeholder="SEO meta description"
                                className="mt-3 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                rows={2}
                                value={form.data.seo_description}
                                onChange={(e) =>
                                    form.setData(
                                        'seo_description',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={form.data.is_enabled}
                                onChange={(e) =>
                                    form.setData('is_enabled', e.target.checked)
                                }
                                className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Page enabled (visible on the public site)
                        </label>
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={onClose}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={form.processing}>
                            Save Page
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    );
}
