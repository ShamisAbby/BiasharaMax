import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface ArticleRow {
    id: string;
    type: string;
    title: string;
    slug: string;
    content: string;
    is_published: boolean;
    view_count: number;
}

interface CategoryRow {
    id: string;
    name: string;
    slug: string;
    icon: string | null;
    articles: ArticleRow[];
}

export default function KnowledgeBase({
    categories,
}: {
    categories: CategoryRow[];
}) {
    const { notify } = useBiNotification();
    const [creating, setCreating] = useState<CategoryRow | null>(null);

    const { data, setData, post, processing, reset } = useForm({
        knowledge_base_category_id: '',
        type: 'article',
        title: '',
        slug: '',
        content: '',
        is_published: false,
    });

    const openCreate = (category: CategoryRow) => {
        reset();
        setData('knowledge_base_category_id', category.id);
        setCreating(category);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        post(
            route('platform.operations.support.knowledge-base.articles.store'),
            {
                onSuccess: () => {
                    setCreating(null);
                    notify('Article created.', 'success');
                },
            },
        );
    };

    const togglePublish = (article: ArticleRow) => {
        router.patch(
            route(
                'platform.operations.support.knowledge-base.articles.update',
                article.id,
            ),
            {
                title: article.title,
                content: article.content,
                is_published: !article.is_published,
            },
        );
    };

    return (
        <PlatformLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Knowledge Base
                    </h1>
                    <Link
                        href={route('platform.operations.support.index')}
                        className="text-sm text-indigo-600 hover:underline"
                    >
                        ← Back to tickets
                    </Link>
                </div>

                <div className="grid gap-6 sm:grid-cols-2">
                    {categories.map((category) => (
                        <BiCard
                            key={category.id}
                            title={category.name}
                            actions={
                                <BiButton
                                    size="sm"
                                    onClick={() => openCreate(category)}
                                >
                                    Add article
                                </BiButton>
                            }
                        >
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {category.articles.map((article) => (
                                    <div
                                        key={article.id}
                                        className="flex items-center justify-between py-2 text-sm"
                                    >
                                        <div>
                                            <p className="text-gray-900 dark:text-gray-100">
                                                {article.title}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {article.type} ·{' '}
                                                {article.view_count} views
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <BiBadge
                                                variant={
                                                    article.is_published
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {article.is_published
                                                    ? 'Published'
                                                    : 'Draft'}
                                            </BiBadge>
                                            <button
                                                onClick={() =>
                                                    togglePublish(article)
                                                }
                                                className="text-indigo-600 hover:underline"
                                            >
                                                Toggle
                                            </button>
                                        </div>
                                    </div>
                                ))}
                                {category.articles.length === 0 && (
                                    <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No articles yet.
                                    </p>
                                )}
                            </div>
                        </BiCard>
                    ))}
                </div>
            </div>

            <BiModal
                show={creating !== null}
                onClose={() => setCreating(null)}
                title={`New article — ${creating?.name ?? ''}`}
                maxWidth="xl"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCreating(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="kb-article-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="kb-article-form"
                    onSubmit={submit}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Title
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.title}
                                onChange={(e) =>
                                    setData('title', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Slug
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.slug}
                                onChange={(e) =>
                                    setData('slug', e.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Type
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={data.type}
                            onChange={(e) => setData('type', e.target.value)}
                        >
                            <option value="article">Article</option>
                            <option value="faq">FAQ</option>
                            <option value="guide">User guide</option>
                        </SelectInput>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Content
                        </label>
                        <textarea
                            className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                            rows={6}
                            value={data.content}
                            onChange={(e) => setData('content', e.target.value)}
                        />
                    </div>
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
