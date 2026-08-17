import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import WebsiteLayout from '@/Layouts/WebsiteLayout';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface ArticleDetail {
    id: string;
    title: string;
    excerpt: string | null;
    body: string;
    featured_image_path: string | null;
    status: 'draft' | 'published';
    seo_title: string | null;
    seo_description: string | null;
    category: { id: string; name: string; slug: string } | null;
    tags: { id: string; name: string; slug: string }[];
}

export default function BlogEdit({
    article,
}: {
    article: ArticleDetail | null;
}) {
    const form = useForm({
        title: article?.title ?? '',
        excerpt: article?.excerpt ?? '',
        body: article?.body ?? '',
        category_name: article?.category?.name ?? '',
        tags: article?.tags.map((t) => t.name).join(', ') ?? '',
        seo_title: article?.seo_title ?? '',
        seo_description: article?.seo_description ?? '',
        status: article?.status ?? 'draft',
        featured_image: null as File | null,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        const tagsArray = form.data.tags
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean);

        form.transform((data) => ({ ...data, tags: tagsArray }));

        const options = { forceFormData: true };

        if (article) {
            form.post(route('website.blog.update', article.id), options);
        } else {
            form.post(route('website.blog.store'), options);
        }
    };

    return (
        <WebsiteLayout title={article ? 'Edit Article' : 'New Article'}>
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {article ? 'Edit Article' : 'New Article'}
                </h3>
                <Link
                    href={route('website.blog.index')}
                    className="text-sm text-indigo-600 hover:underline"
                >
                    Back to articles
                </Link>
            </div>

            <form
                onSubmit={submit}
                className="space-y-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
            >
                <div>
                    <InputLabel htmlFor="title" value="Title" />
                    <TextInput
                        id="title"
                        className="mt-1 block w-full"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                    <InputError message={form.errors.title} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="excerpt" value="Excerpt" />
                    <textarea
                        id="excerpt"
                        rows={2}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        value={form.data.excerpt}
                        onChange={(e) =>
                            form.setData('excerpt', e.target.value)
                        }
                    />
                    <InputError
                        message={form.errors.excerpt}
                        className="mt-2"
                    />
                </div>

                <div>
                    <InputLabel htmlFor="body" value="Body" />
                    <textarea
                        id="body"
                        rows={12}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                    />
                    <InputError message={form.errors.body} className="mt-2" />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="category_name" value="Category" />
                        <TextInput
                            id="category_name"
                            placeholder="e.g. Announcements"
                            className="mt-1 block w-full"
                            value={form.data.category_name}
                            onChange={(e) =>
                                form.setData('category_name', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <InputLabel
                            htmlFor="tags"
                            value="Tags (comma-separated)"
                        />
                        <TextInput
                            id="tags"
                            placeholder="e.g. tips, news"
                            className="mt-1 block w-full"
                            value={form.data.tags}
                            onChange={(e) =>
                                form.setData('tags', e.target.value)
                            }
                        />
                    </div>
                </div>

                <div>
                    <InputLabel
                        htmlFor="featured_image"
                        value="Featured Image"
                    />
                    {article?.featured_image_path && (
                        <img
                            src={article.featured_image_path}
                            alt={article.title}
                            className="mt-2 h-32 w-auto rounded-lg object-cover"
                        />
                    )}
                    <input
                        id="featured_image"
                        type="file"
                        accept="image/*"
                        className="mt-2 block w-full text-sm text-gray-600 dark:text-gray-300"
                        onChange={(e) =>
                            form.setData(
                                'featured_image',
                                e.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <InputError
                        message={form.errors.featured_image}
                        className="mt-2"
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="seo_title" value="SEO Title" />
                        <TextInput
                            id="seo_title"
                            className="mt-1 block w-full"
                            value={form.data.seo_title}
                            onChange={(e) =>
                                form.setData('seo_title', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="status" value="Status" />
                        <SelectInput
                            id="status"
                            className="mt-1 block w-full"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData(
                                    'status',
                                    e.target.value as 'draft' | 'published',
                                )
                            }
                        >
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </SelectInput>
                    </div>
                </div>

                <div>
                    <InputLabel
                        htmlFor="seo_description"
                        value="SEO Description"
                    />
                    <textarea
                        id="seo_description"
                        rows={2}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        value={form.data.seo_description}
                        onChange={(e) =>
                            form.setData('seo_description', e.target.value)
                        }
                    />
                </div>

                <div className="flex justify-end gap-3">
                    <SecondaryButton
                        type="button"
                        onClick={() => window.history.back()}
                    >
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton type="submit" disabled={form.processing}>
                        {article ? 'Save Changes' : 'Create Article'}
                    </PrimaryButton>
                </div>
            </form>
        </WebsiteLayout>
    );
}
