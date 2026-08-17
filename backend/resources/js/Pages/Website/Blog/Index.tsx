import Badge from '@/Components/Badge';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import WebsiteLayout from '@/Layouts/WebsiteLayout';
import { Link, router } from '@inertiajs/react';

type ArticleStatus = 'draft' | 'published';

interface ArticleListItem {
    id: string;
    title: string;
    slug: string;
    status: ArticleStatus;
    published_at: string | null;
    category: { id: string; name: string; slug: string } | null;
    created_at: string;
}

export default function BlogIndex({
    articles,
    filters,
}: {
    articles: {
        data: ArticleListItem[];
        meta: {
            links: { url: string | null; label: string; active: boolean }[];
        };
    };
    filters: { status?: string };
}) {
    const askConfirm = useConfirm();
    const applyFilter = (status: string) => {
        router.get(
            route('website.blog.index'),
            { status: status || undefined },
            { preserveState: true },
        );
    };

    const togglePublish = (article: ArticleListItem) => {
        const routeName =
            article.status === 'published'
                ? 'website.blog.unpublish'
                : 'website.blog.publish';
        router.post(route(routeName, article.id), {}, { preserveScroll: true });
    };

    const destroy = (article: ArticleListItem) => {
        askConfirm({
            title: `Delete "${article.title}"?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('website.blog.destroy', article.id));
            },
        });
    };

    return (
        <WebsiteLayout title="Blog">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Blog Articles
                </h3>
                <div className="flex items-center gap-3">
                    <SelectInput
                        value={filters.status ?? ''}
                        onChange={(e) => applyFilter(e.target.value)}
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </SelectInput>
                    <Link
                        href={route('website.blog.create')}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        New Article
                    </Link>
                </div>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                Title
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                Category
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                Published
                            </th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {articles.data.map((article) => (
                            <tr key={article.id}>
                                <td className="px-4 py-3 text-sm">
                                    <Link
                                        href={route(
                                            'website.blog.show',
                                            article.id,
                                        )}
                                        className="font-medium text-indigo-600 hover:underline"
                                    >
                                        {article.title}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {article.category?.name ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <Badge
                                        variant={
                                            article.status === 'published'
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {article.status}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {article.published_at
                                        ? new Date(
                                              article.published_at,
                                          ).toLocaleDateString()
                                        : '—'}
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <div className="flex justify-end gap-2">
                                        <SecondaryButton
                                            onClick={() =>
                                                togglePublish(article)
                                            }
                                        >
                                            {article.status === 'published'
                                                ? 'Unpublish'
                                                : 'Publish'}
                                        </SecondaryButton>
                                        <button
                                            onClick={() => destroy(article)}
                                            className="text-rose-600 hover:underline"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {articles.data.length === 0 && (
                    <p className="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No articles yet — create your first blog post.
                    </p>
                )}
            </div>

            {articles.meta.links.length > 3 && (
                <div className="flex flex-wrap justify-center gap-1">
                    {articles.meta.links.map((link, index) => (
                        <button
                            key={index}
                            disabled={!link.url}
                            onClick={() =>
                                link.url &&
                                router.get(
                                    link.url,
                                    {},
                                    { preserveState: true },
                                )
                            }
                            className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </WebsiteLayout>
    );
}
