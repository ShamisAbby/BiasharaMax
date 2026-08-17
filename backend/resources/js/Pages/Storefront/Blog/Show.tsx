import { Head } from '@inertiajs/react';
import StorefrontLayout, { StorefrontBusiness } from '../StorefrontLayout';

interface BlogArticle {
    title: string;
    slug: string;
    body: string;
    featured_image_path: string | null;
    published_at: string;
    seo_title: string | null;
    seo_description: string | null;
    category: { name: string; slug: string } | null;
    tags: { name: string; slug: string }[];
    author_name: string | null;
}

export default function StorefrontBlogShow({
    business,
    article,
}: {
    business: StorefrontBusiness;
    article: BlogArticle;
}) {
    return (
        <StorefrontLayout
            business={business}
            title={article.seo_title ?? article.title}
        >
            <Head>
                {article.seo_description && (
                    <meta
                        name="description"
                        content={article.seo_description}
                    />
                )}
            </Head>

            <article className="mx-auto max-w-3xl">
                {article.category && (
                    <span className="text-xs font-medium uppercase tracking-wide text-[var(--brand-primary)]">
                        {article.category.name}
                    </span>
                )}
                <h1 className="mt-2 text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                    {article.title}
                </h1>
                <p className="mt-2 text-sm text-[var(--brand-muted)]">
                    {article.author_name && <>By {article.author_name} · </>}
                    {new Date(article.published_at).toLocaleDateString()}
                </p>

                {article.featured_image_path && (
                    <img
                        src={article.featured_image_path}
                        alt={article.title}
                        className="mt-6 w-full rounded-2xl object-cover"
                    />
                )}

                <div className="mt-8 max-w-none whitespace-pre-wrap text-[var(--brand-text)]">
                    {article.body}
                </div>

                {article.tags.length > 0 && (
                    <div className="mt-8 flex flex-wrap gap-2">
                        {article.tags.map((tag) => (
                            <span
                                key={tag.slug}
                                className="rounded-full bg-[var(--brand-surface)] px-3 py-1 text-xs text-[var(--brand-text)]"
                            >
                                #{tag.name}
                            </span>
                        ))}
                    </div>
                )}
            </article>
        </StorefrontLayout>
    );
}
