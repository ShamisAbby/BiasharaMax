<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Support\AdminSurface;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Switching between the Inertia admin (/admin) and the Filament panel
 * (/platform).
 *
 * The two risks worth testing are not "does the preference save".
 *
 *  1. **A redirect loop.** Both surfaces redirect at their root. If each
 *     one enforced the preference rather than merely honouring it at the
 *     landing point, /admin would bounce to /platform and back forever —
 *     unrecoverable in a browser, and invisible in code review because
 *     each redirect looks correct on its own.
 *  2. **A one-way door.** The preference must not fence anyone out of the
 *     other surface, because several screens exist on only one of them.
 *
 * Both are asserted below by visiting the far surface while the
 * preference points the other way.
 */
class AdminSurfaceSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_an_admin_who_has_never_chosen_defaults_to_the_inertia_admin(): void
    {
        $admin = PlatformUser::factory()->create(['preferred_admin_surface' => null]);

        $this->assertSame(AdminSurface::INERTIA, $admin->preferredAdminSurface());

        $this->actingAs($admin, 'platform')
            ->get('/admin')
            ->assertRedirect(route('platform.dashboard'));
    }

    /**
     * A plain form post — the Blade switcher in the Filament topbar —
     * gets an ordinary redirect.
     */
    public function test_switching_from_a_plain_form_post_redirects_normally(): void
    {
        $admin = PlatformUser::factory()->create();

        $this->actingAs($admin, 'platform')
            ->post(route('platform.preferences.admin-surface'), ['surface' => AdminSurface::FILAMENT])
            ->assertRedirect('/platform');

        $this->assertSame(AdminSurface::FILAMENT, $admin->fresh()->preferredAdminSurface());
    }

    /**
     * The regression that shipped broken.
     *
     * An Inertia request must NOT get a 302. Inertia follows redirects
     * itself, asking the destination for an Inertia payload — and
     * /platform is a Livewire app that answers with HTML. The client
     * cannot parse it, so in development it renders the Filament panel
     * inside Inertia's error overlay on top of /admin, and in production
     * it fails silently. Either way the switch never completes.
     *
     * 409 + `X-Inertia-Location` is the protocol's own answer: it tells
     * the client to perform a real browser navigation instead.
     *
     * The first version of this test asserted `assertRedirect('/platform')`
     * for both cases, which is why it passed while the feature did not
     * work — a reminder that asserting the response an implementation
     * happens to give is not the same as asserting the one the client
     * needs.
     */
    public function test_switching_from_inertia_returns_a_location_response_not_a_redirect(): void
    {
        $admin = PlatformUser::factory()->create();

        $response = $this->actingAs($admin, 'platform')
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
            ->post(route('platform.preferences.admin-surface'), ['surface' => AdminSurface::FILAMENT]);

        $response->assertStatus(409);
        // Verbatim, not url()'d — Inertia sets the header to whatever
        // string it was handed.
        $response->assertHeader('X-Inertia-Location', '/platform');

        $this->assertSame(AdminSurface::FILAMENT, $admin->fresh()->preferredAdminSurface());
    }

    public function test_the_admin_root_follows_the_preference(): void
    {
        $admin = PlatformUser::factory()->create([
            'preferred_admin_surface' => AdminSurface::FILAMENT,
        ]);

        $this->actingAs($admin, 'platform')
            ->get('/admin')
            ->assertRedirect('/platform');
    }

    /**
     * The loop guard.
     *
     * Only the bare root redirects across. Every other /admin URL must
     * serve the Inertia admin regardless of the preference — otherwise
     * the two surfaces hand each other back and forth.
     */
    public function test_a_filament_preference_does_not_lock_an_admin_out_of_the_inertia_admin(): void
    {
        $admin = PlatformUser::factory()->create([
            'preferred_admin_surface' => AdminSurface::FILAMENT,
        ]);

        $this->actingAs($admin, 'platform')
            ->get(route('platform.dashboard'))
            ->assertOk();
    }

    public function test_an_inertia_preference_does_not_lock_an_admin_out_of_the_filament_panel(): void
    {
        $admin = PlatformUser::factory()->create([
            'preferred_admin_surface' => AdminSurface::INERTIA,
        ]);

        // Not a redirect back to /admin: the panel must remain reachable
        // for anyone following a link or a bookmark into it.
        $this->actingAs($admin, 'platform')
            ->get('/platform')
            ->assertSuccessful();
    }

    public function test_signing_in_lands_on_the_chosen_surface(): void
    {
        $admin = PlatformUser::factory()->create([
            'email' => 'surface@example.com',
            'password' => bcrypt('Password123!'),
            'preferred_admin_surface' => AdminSurface::FILAMENT,
        ]);

        $this->post(route('platform.login'), [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertRedirect('/platform');
    }

    /**
     * A deep link still wins over the preference. Someone who followed a
     * link to a specific screen wants that screen, not wherever they
     * usually start.
     */
    public function test_an_intended_url_beats_the_preference(): void
    {
        $admin = PlatformUser::factory()->create([
            'email' => 'intended@example.com',
            'password' => bcrypt('Password123!'),
            'preferred_admin_surface' => AdminSurface::FILAMENT,
        ]);

        // Bounced off a guarded page first, which is what sets `intended`.
        $this->get(route('platform.businesses.index'))->assertRedirect(route('platform.login'));

        $this->post(route('platform.login'), [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('platform.businesses.index'));
    }

    /**
     * A Filament deep link must not survive the Inertia login.
     *
     * Both surfaces share the `platform` guard, so a guest who hits any
     * `/platform` URL is bounced to `/admin/login` by
     * RedirectFilamentGuestsToPlatformLogin — and Laravel stores that
     * Filament URL as `intended`. Following it dropped the administrator
     * into a different application from the one whose login form they
     * had just filled in, ignoring their surface preference on the way.
     *
     * It reads as the preference being broken, which is why this is
     * asserted rather than left to the `intended` default.
     */
    public function test_a_filament_deep_link_does_not_survive_the_admin_login(): void
    {
        $admin = PlatformUser::factory()->create([
            'email' => 'crosssurface@example.com',
            'password' => bcrypt('Password123!'),
            'preferred_admin_surface' => AdminSurface::INERTIA,
        ]);

        // What the Filament auth middleware does to a signed-out visitor.
        $this->withSession(['url.intended' => url('/platform/support-tickets')]);

        $this->post(route('platform.login'), [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('platform.dashboard'));
    }

    public function test_the_login_page_is_never_itself_the_destination(): void
    {
        $admin = PlatformUser::factory()->create([
            'email' => 'loopy@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->withSession(['url.intended' => url('/admin/login')]);

        $this->post(route('platform.login'), [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('platform.dashboard'));
    }

    public function test_an_unknown_surface_is_rejected(): void
    {
        $admin = PlatformUser::factory()->create([
            'preferred_admin_surface' => AdminSurface::INERTIA,
        ]);

        $this->actingAs($admin, 'platform')
            ->from(route('platform.dashboard'))
            ->post(route('platform.preferences.admin-surface'), ['surface' => 'wordpress'])
            ->assertSessionHasErrors('surface');

        $this->assertSame(AdminSurface::INERTIA, $admin->fresh()->preferredAdminSurface());
    }

    /**
     * A value written before a surface was renamed or retired must not
     * strand the account somewhere that no longer resolves.
     */
    public function test_a_stale_stored_value_falls_back_to_the_default(): void
    {
        $admin = PlatformUser::factory()->create();

        // Written past the model, as a legacy row or a hand-edit would be.
        DB::table('platform_users')
            ->where('id', $admin->id)
            ->update(['preferred_admin_surface' => 'retired-surface']);

        $this->assertSame(AdminSurface::INERTIA, $admin->fresh()->preferredAdminSurface());

        $this->actingAs($admin->fresh(), 'platform')
            ->get('/admin')
            ->assertRedirect(route('platform.dashboard'));
    }

    public function test_a_guest_at_the_root_is_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('platform.login'));
    }

    /**
     * The switcher tells an admin what is only on the other side. If that
     * list silently empties, the warning becomes a lie by omission — so
     * it is asserted rather than left as prose in a Blade file.
     */
    public function test_the_feature_gap_is_declared_for_the_switcher(): void
    {
        $missingFromFilament = AdminSurface::missingFrom(AdminSurface::FILAMENT);

        $this->assertNotEmpty(
            $missingFromFilament,
            'The Filament panel is still missing screens; the switcher must be able to name them.',
        );

        $this->assertContains('RBAC permission matrix', $missingFromFilament);
    }
}
