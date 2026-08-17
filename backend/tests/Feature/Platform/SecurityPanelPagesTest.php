<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Filament\Pages\DeveloperCenter;
use App\Domain\Platform\Filament\Pages\SecurityCenter;
use App\Domain\Platform\Filament\Pages\SystemMonitoring;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * The three screens that existed only on the old /admin Inertia surface
 * and now exist in the Filament panel too.
 *
 * The point is not that they render — it's that they render *behind the
 * same permissions the Inertia routes enforced*. Porting a screen to a
 * new panel is exactly where an authorization check gets quietly
 * dropped: the Inertia versions were gated by route middleware, and a
 * Filament page has no route middleware at all. Its only guard is
 * `canAccess()`, which is easy to forget and impossible to notice
 * missing, because the page looks perfectly fine to whoever wrote it.
 *
 * Guards are asserted through the static methods rather than by driving
 * Livewire. Those methods are what both the navigation and the page
 * itself consult, so a wrong permission slug or an absent guard fails
 * here — without the test depending on Filament's panel-context setup,
 * which is unrelated plumbing to what is actually at risk.
 */
class SecurityPanelPagesTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    /** @var array<int, class-string> */
    private const PAGES = [
        SystemMonitoring::class,
        SecurityCenter::class,
        DeveloperCenter::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_an_unrestricted_admin_can_access_all_three_pages(): void
    {
        $this->actingAs(PlatformUser::factory()->create(), 'platform');

        foreach (self::PAGES as $page) {
            $this->assertTrue($page::canAccess(), $page.' should be reachable by an unrestricted admin.');
        }
    }

    /**
     * A role that grants nothing must reach none of them.
     *
     * Asserted per page rather than once, because each carries its own
     * `canAccess()` — one of the three losing its guard would not show up
     * in a test that only checked another.
     */
    public function test_an_admin_without_permissions_is_refused_every_page(): void
    {
        $role = PlatformRole::query()->create([
            'name' => 'No Security',
            'slug' => 'no-security-access',
            'is_system' => false,
        ]);

        $this->actingAs(PlatformUser::factory()->create(['platform_role_id' => $role->id]), 'platform');

        foreach (self::PAGES as $page) {
            $this->assertFalse($page::canAccess(), $page.' must not be reachable without its permission.');
        }
    }

    /**
     * The distinction that matters most on these screens.
     *
     * Security Center can block an IP, unblock one, unlock an account and
     * resolve an alert. Every one of those is gated on `security.manage`
     * while the page itself only needs `security.view` — so an auditor
     * granted read access must be able to look without being able to
     * change anything.
     */
    public function test_view_permission_alone_does_not_grant_the_actions(): void
    {
        $role = PlatformRole::query()->create([
            'name' => 'Security Viewer',
            'slug' => 'security-viewer',
            'is_system' => false,
        ]);

        $role->permissions()->attach(
            Permission::query()->where('slug', 'security.view')->firstOrFail()->id,
        );

        $this->actingAs(PlatformUser::factory()->create(['platform_role_id' => $role->id]), 'platform');

        $this->assertTrue(SecurityCenter::canAccess(), 'security.view should open the page.');
        $this->assertFalse(SecurityCenter::canManage(), 'security.view must not grant blocking or resolving.');
    }

    public function test_developer_center_separates_viewing_from_clearing_the_cache(): void
    {
        $role = PlatformRole::query()->create([
            'name' => 'Developer Viewer',
            'slug' => 'developer-viewer',
            'is_system' => false,
        ]);

        $role->permissions()->attach(
            Permission::query()->where('slug', 'developer.view')->firstOrFail()->id,
        );

        $this->actingAs(PlatformUser::factory()->create(['platform_role_id' => $role->id]), 'platform');

        $this->assertTrue(DeveloperCenter::canAccess());
        $this->assertFalse(DeveloperCenter::canManage(), 'developer.view must not grant a cache flush.');
    }

    public function test_a_guest_reaches_none_of_them(): void
    {
        foreach (self::PAGES as $page) {
            $this->assertFalse($page::canAccess(), $page.' must refuse an unauthenticated visitor.');
        }
    }

    public function test_all_three_sit_in_the_security_navigation_group(): void
    {
        foreach (self::PAGES as $page) {
            $this->assertSame(
                'Security',
                (new \ReflectionClass($page))->getProperty('navigationGroup')->getDefaultValue(),
                $page.' must sit in the Security group.',
            );
        }
    }

    /**
     * The route list is over a thousand entries, capped at 200 for
     * rendering, and the filter is what makes the rest reachable — so a
     * broken filter silently hides most of the platform behind a cap that
     * looks deliberate.
     */
    public function test_the_route_filter_narrows_the_list(): void
    {
        $page = new DeveloperCenter;
        $page->routeFilter = 'sync/products';

        $routes = $page->getRoutes();

        $this->assertNotEmpty($routes, 'Expected the desktop sync route to be findable.');

        foreach ($routes as $route) {
            $this->assertTrue(
                str_contains($route['uri'], 'sync/products')
                    || str_contains((string) $route['name'], 'sync/products'),
                'Filter returned a non-matching route: '.$route['uri'],
            );
        }
    }

    public function test_an_empty_route_filter_is_capped_rather_than_unbounded(): void
    {
        $page = new DeveloperCenter;

        $this->assertLessThanOrEqual(200, count($page->getRoutes()));
        $this->assertGreaterThan(200, $page->getRouteCount(), 'Expected more routes than the render cap.');
    }

    /**
     * Revoking is scoped to the caller's own tokens.
     *
     * The page passes a token id straight from the rendered list, so
     * without the ownership filter any admin could revoke any token on
     * the platform by id — and the only visible difference is that
     * somebody else's integration stops working.
     */
    public function test_revoking_a_token_cannot_reach_another_admins_tokens(): void
    {
        $owner = PlatformUser::factory()->create();
        $other = PlatformUser::factory()->create();

        $victimToken = $other->createToken('Theirs')->accessToken;

        $this->actingAs($owner, 'platform');

        $page = new DeveloperCenter;
        $page->revokeToken((string) $victimToken->id);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $victimToken->id]);
    }

    public function test_an_admin_can_revoke_their_own_token(): void
    {
        $admin = PlatformUser::factory()->create();
        $token = $admin->createToken('Mine')->accessToken;

        $this->actingAs($admin, 'platform');

        $page = new DeveloperCenter;
        $page->revokeToken((string) $token->id);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_a_viewer_cannot_revoke_or_delete(): void
    {
        $role = PlatformRole::query()->create([
            'name' => 'Developer Viewer 2',
            'slug' => 'developer-viewer-2',
            'is_system' => false,
        ]);
        $role->permissions()->attach(
            Permission::query()->where('slug', 'developer.view')->firstOrFail()->id,
        );

        $viewer = PlatformUser::factory()->create(['platform_role_id' => $role->id]);
        $token = $viewer->createToken('Mine')->accessToken;

        $this->actingAs($viewer, 'platform');

        $page = new DeveloperCenter;

        try {
            $page->revokeToken((string) $token->id);
            $this->fail('developer.view alone must not permit revoking a token.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
    }
}
