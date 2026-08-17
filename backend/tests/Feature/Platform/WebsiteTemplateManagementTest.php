<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\RBAC\Models\PlatformRole;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class WebsiteTemplateManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_create_a_template(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.operations.website-templates.store'), [
            'name' => 'Retail Default',
            'slug' => 'retail-default',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('website_templates', ['slug' => 'retail-default', 'status' => 'draft']);
    }

    public function test_publishing_a_template_creates_a_version_snapshot(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $template = WebsiteTemplate::factory()->create();
        $template->pages()->create(['type' => 'homepage', 'title' => 'Home', 'slug' => 'home']);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.website-templates.publish', $template->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(WebsiteTemplate::STATUS_PUBLISHED, $template->fresh()->status);
        $this->assertSame(1, $template->versions()->count());
    }

    public function test_cloning_a_template_copies_its_pages(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $template = WebsiteTemplate::factory()->create();
        $template->pages()->create(['type' => 'homepage', 'title' => 'Home', 'slug' => 'home']);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.website-templates.clone', $template->id), ['name' => 'Retail Clone'])
            ->assertSessionHasNoErrors();

        $clone = WebsiteTemplate::query()->where('name', 'Retail Clone')->first();
        $this->assertNotNull($clone);
        $this->assertSame(1, $clone->pages()->count());
        $this->assertSame(WebsiteTemplate::STATUS_DRAFT, $clone->status);
    }

    public function test_pages_can_be_added_and_removed(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $template = WebsiteTemplate::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.operations.website-templates.pages.store', $template->id), [
            'type' => 'about',
            'title' => 'About Us',
            'slug' => 'about-us',
        ])->assertSessionHasNoErrors();

        $page = $template->pages()->first();
        $this->assertNotNull($page);

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.operations.website-templates.pages.destroy', [$template->id, $page->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $template->pages()->count());
    }

    public function test_platform_admin_without_permission_is_forbidden(): void
    {
        $role = PlatformRole::query()->create(['name' => 'Other', 'slug' => 'other-role', 'is_system' => false]);
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.operations.website-templates.index'))
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_website_templates(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.operations.website-templates.index'))
            ->assertRedirect(route('platform.login'));
    }
}
