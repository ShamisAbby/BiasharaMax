import { Link, router } from '@inertiajs/react';
import StorefrontLayout, { StorefrontBusiness } from '../StorefrontLayout';

interface BlogListArticle {
    title: string;
    slug: string;
    excerpt: string | null;
    featured_image_path: string | null;
    published_at: string;
    category: { name: string; slug: string } | null;
}

export default function StorefrontBlogIndex({
    business,
    articles,
}: {
    business: StorefrontBusiness;
    articles: {
        data: BlogListArticle[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}) {
    return (
        <StorefrontLayout business={business} title="Blog">
            <h1 className="text-3xl font-[var(--font-heading)] font-bold text-[var(--brand-text)]">
                Blog
            </h1>

            <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {articles.data.map((article) => (
                    <Link
                        key={article.slug}
                        href={route('public.website.blog.show', [
                            business.slug,
                            article.slug,
                        ])}
                        className="group overflow-hidden rounded-2xl bg-[var(--brand-surface)] ring-1 ring-black/5 transition hover:shadow-md"
                    >
                        <div className="flex aspect-video items-center justify-center bg-black/5">
                            {article.featured_image_path ? (
                                <img
                                    src={article.featured_image_path}
                                    alt={article.title}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <span className="text-sm text-[var(--brand-muted)]">
                                    No image
                                </span>
                            )}
                        </div>
                        <div className="p-4">
                            {article.category && (
                                <span className="text-xs font-medium uppercase tracking-wide text-[var(--brand-primary)]">
                                    {article.category.name}
                                </span>
                            )}
                            <h3 className="mt-1 text-base font-[var(--font-heading)] font-semibold text-[var(--brand-text)]">
                                {article.title}
                            </h3>
                            {article.excerpt && (
                                <p className="mt-1 text-sm text-[var(--brand-muted)]">
                                    {article.excerpt}
                                </p>
                            )}
                        </div>
                    </Link>
                ))}
            </div>

            {articles.data.length === 0 && (
                <p className="mt-12 text-center text-sm text-[var(--brand-muted)]">
                    No articles published yet.
                </p>
            )}

            {articles.links.length > 3 && (
                <div className="mt-8 flex flex-wrap justify-center gap-1">
                    {articles.links.map((link, index) => (
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
                            className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-[var(--brand-primary)] text-white' : 'text-[var(--brand-text)]/70 hover:bg-black/5 disabled:opacity-40'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </StorefrontLayout>
    );
}
