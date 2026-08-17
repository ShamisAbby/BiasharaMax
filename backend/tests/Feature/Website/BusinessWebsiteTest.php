<?php

namespace Tests\Feature\Website;

use App\Domain\Authentication\Models\User;
use App\Domain\RBAC\Models\Role;
use App\Domain\Website\Models\BusinessWebsite;
use App\Domain\Website\Models\BusinessWebsitePage;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\WebsiteTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class BusinessWebsiteTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BusinessTypeSeeder::class, WebsiteTemplateSeeder::class, PermissionSeeder::class]);
    }

    public function test_visiting_the_dashboard_seeds_a_business_website_from_the_assigned_template(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->get('/website')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Website/Dashboard')
                ->where('website.status', 'draft')
                ->where('website.template_name', 'Retail Modern')
                ->has('website.pages', 6)
            );

        $website = BusinessWebsite::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame(6, BusinessWebsitePage::query()->where('business_website_id', $website->id)->count());
    }

    public function test_owner_can_edit_a_page_and_changes_persist(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->actingAs($owner)->get('/website');

        $website = BusinessWebsite::query()->where('business_id', $business->id)->firstOrFail();
        $homepage = $website->pages()->where('type', 'homepage')->firstOrFail();

        $this->actingAs($owner)->patch("/website/{$website->id}/pages/{$homepage->id}", [
            'title' => 'Home',
            'content' => [
                'hero' => ['headline' => 'My Custom Headline', 'subheadline' => 'Custom sub'],
                'features' => [],
            ],
            'is_enabled' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('My Custom Headline', $homepage->refresh()->content['hero']['headline']);
    }

    public function test_publishing_makes_the_business_website_override_the_shared_template_publicly(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->actingAs($owner)->get('/website');

        $website = BusinessWebsite::query()->where('business_id', $business->id)->firstOrFail();
        $homepage = $website->pages()->where('type', 'homepage')->firstOrFail();
        $homepage->update(['content' => ['hero' => ['headline' => 'Published Override Headline'], 'features' => []]]);

        $this->actingAs($owner)->post("/website/{$website->id}/publish")->assertSessionHasNoErrors();
        $this->assertSame('published', $website->refresh()->status);

        $this->get("/site/{$business->slug}")->assertInertia(fn ($page) => $page
            ->where('template.pages.0.content.hero.headline', 'Published Override Headline')
        );
    }

    public function test_an_unpublished_business_website_does_not_affect_the_public_site(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->actingAs($owner)->get('/website');

        $website = BusinessWebsite::query()->where('business_id', $business->id)->firstOrFail();
        $homepage = $website->pages()->where('type', 'homepage')->firstOrFail();
        $homepage->update(['content' => ['hero' => ['headline' => 'Still Draft Headline'], 'features' => []]]);

        $this->get("/site/{$business->slug}")->assertInertia(fn ($page) => $page
            ->where('template.pages.0.content.hero.headline', 'Everything you need, all in one place')
        );
    }

    public function test_employee_without_website_manage_permission_cannot_publish(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->actingAs($owner)->get('/website');
        $website = BusinessWebsite::query()->where('business_id', $business->id)->firstOrFail();

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post("/website/{$website->id}/publish")->assertForbidden();
        $this->assertSame('draft', $website->refresh()->status);
    }

    public function test_owner_can_update_site_wide_seo_settings(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->actingAs($owner)->get('/website');
        $website = BusinessWebsite::query()->where('business_id', $business->id)->firstOrFail();

        $this->actingAs($owner)->patch("/website/{$website->id}", [
            'seo_title' => 'Best Shop in Town',
            'seo_description' => 'Quality goods at honest prices.',
        ])->assertSessionHasNoErrors();

        $website->refresh();
        $this->assertSame('Best Shop in Town', $website->seo_title);
        $this->assertSame('Quality goods at honest prices.', $website->seo_description);
    }

    public function test_dashboard_summary_reflects_real_online_orders_and_open_enquiries(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = \App\Domain\Business\Models\Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = \App\Domain\Business\Models\Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $product = \App\Domain\Inventory\Models\Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
            'status' => 'active', 'visibility' => 'visible',
        ]);
        \App\Domain\Inventory\Models\Inventory::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'quantity' => 10,
        ]);

        $this->post("/site/{$business->slug}/cart", ['product_id' => $product->id, 'quantity' => 1]);
        $this->post("/site/{$business->slug}/checkout", [
            'name' => 'Jane Buyer', 'phone' => '0700000000', 'delivery_address' => 'Addr',
            'payment_method' => 'pay_on_delivery',
        ]);

        \App\Domain\Website\Models\ProductEnquiry::create([
            'business_id' => $business->id, 'name' => 'Curious', 'message' => 'Question?',
        ]);

        $this->actingAs($owner)->get('/website')->assertInertia(fn ($page) => $page
            ->where('summary.online_orders_this_month', 1)
            ->where('summary.online_revenue_this_month', 1000)
            ->where('summary.open_enquiries_count', 1)
            ->has('recentOrders', 1)
        );
    }
}
