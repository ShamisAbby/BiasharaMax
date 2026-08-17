<?php

namespace Tests\Feature\Website;

use App\Domain\Authentication\Models\User;
use App\Domain\RBAC\Models\Role;
use App\Domain\Website\Models\Article;
use App\Domain\Website\Models\ArticleCategory;
use App\Domain\Website\Models\ArticleTag;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\WebsiteTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BusinessTypeSeeder::class, WebsiteTemplateSeeder::class, PermissionSeeder::class]);
    }

    public function test_owner_can_create_a_draft_article_with_a_new_category_and_tags(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/website/blog', [
            'title' => 'How We Started',
            'excerpt' => 'A short story.',
            'body' => 'The full story of our business.',
            'category_name' => 'Announcements',
            'tags' => ['news', 'story'],
            'status' => 'draft',
        ])->assertSessionHasNoErrors();

        $article = Article::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame('How We Started', $article->title);
        $this->assertSame('draft', $article->status);
        $this->assertNull($article->published_at);
        $this->assertSame('Announcements', $article->category->name);
        $this->assertSame(['news', 'story'], $article->tags->pluck('name')->sort()->values()->toArray());

        $this->assertSame(1, ArticleCategory::query()->where('business_id', $business->id)->count());
        $this->assertSame(2, ArticleTag::query()->where('business_id', $business->id)->count());
    }

    public function test_creating_an_article_with_status_published_sets_published_at(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/website/blog', [
            'title' => 'Launch Day',
            'body' => 'We are live!',
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $article = Article::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame('published', $article->status);
        $this->assertNotNull($article->published_at);
    }

    public function test_owner_can_publish_and_unpublish_an_article(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $article = Article::create([
            'business_id' => $business->id,
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'body' => 'Content',
        ]);

        $this->actingAs($owner)->post("/website/blog/{$article->id}/publish")->assertSessionHasNoErrors();
        $this->assertSame('published', $article->refresh()->status);
        $this->assertNotNull($article->published_at);

        $this->actingAs($owner)->post("/website/blog/{$article->id}/unpublish")->assertSessionHasNoErrors();
        $this->assertSame('draft', $article->refresh()->status);
    }

    public function test_only_published_articles_are_visible_on_the_public_blog(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        Article::create([
            'business_id' => $business->id, 'title' => 'Published Post', 'slug' => 'published-post',
            'body' => 'Visible content', 'status' => 'published', 'published_at' => now(),
        ]);
        Article::create([
            'business_id' => $business->id, 'title' => 'Draft Post', 'slug' => 'draft-post',
            'body' => 'Hidden content', 'status' => 'draft',
        ]);

        $this->get("/site/{$business->slug}/blog")->assertInertia(fn ($page) => $page
            ->has('articles.data', 1)
            ->where('articles.data.0.title', 'Published Post')
        );

        $this->get("/site/{$business->slug}/blog/published-post")->assertOk();
        $this->get("/site/{$business->slug}/blog/draft-post")->assertNotFound();
    }

    public function test_employee_without_website_manage_permission_cannot_create_an_article(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post('/website/blog', [
            'title' => 'Unauthorized Post',
            'body' => 'Should not be created.',
        ])->assertForbidden();

        $this->assertSame(0, Article::query()->where('business_id', $business->id)->count());
    }

    public function test_owner_can_update_an_existing_article(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $article = Article::create([
            'business_id' => $business->id,
            'title' => 'Original Title',
            'slug' => 'original-title',
            'body' => 'Original body',
        ]);

        $this->actingAs($owner)->post("/website/blog/{$article->id}", [
            'title' => 'Updated Title',
            'body' => 'Updated body',
        ])->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame('Updated Title', $article->title);
        $this->assertSame('Updated body', $article->body);
        $this->assertSame('updated-title', $article->slug);
    }

    public function test_business_with_a_published_article_reports_has_blog_on_the_public_site(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $this->get("/site/{$business->slug}")->assertInertia(fn ($page) => $page
            ->where('business.has_blog', false)
        );

        Article::create([
            'business_id' => $business->id, 'title' => 'Published Post', 'slug' => 'published-post',
            'body' => 'Visible content', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->get("/site/{$business->slug}")->assertInertia(fn ($page) => $page
            ->where('business.has_blog', true)
        );
    }
}
