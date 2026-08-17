<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Services\PlatformStatusBadgeService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * The health badge both admin surfaces show.
 *
 * Written because the two used to contradict each other: the Filament
 * panel computed a real status, while the Inertia admin rendered the
 * literal text "Operational" next to a hardcoded green dot. An operator
 * could see Redis down on /platform and "Operational" on /admin at the
 * same moment.
 *
 * What matters is not that the badge renders — it is that exactly one
 * implementation produces it. So these assert the shape reaching the
 * React top bar and the fact that it comes from the shared service,
 * rather than re-testing the health calculation itself.
 */
class PlatformStatusBadgeTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_the_badge_is_shared_with_the_inertia_admin(): void
    {
        $admin = PlatformUser::factory()->create();

        $this->actingAs($admin, 'platform')
            ->get(route('platform.dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->has('platformAuth.status.color')
                ->has('platformAuth.status.label')
                ->has('platformAuth.status.title')
            );
    }

    /**
     * The regression guard with teeth.
     *
     * "Operational" must be a computed outcome, never a constant. If the
     * label can only ever be that one value, the indicator is decorative
     * — which is precisely the bug this replaced.
     */
    public function test_the_label_is_one_of_the_three_real_states(): void
    {
        $status = app(PlatformStatusBadgeService::class)->current();

        $this->assertContains($status['label'], ['Operational', 'Degraded', 'Down']);
        $this->assertContains($status['color'], ['success', 'warning', 'danger']);

        // The title names the two dependencies, which is what makes the
        // badge diagnosable rather than merely coloured.
        $this->assertStringContainsString('DB ', $status['title']);
        $this->assertStringContainsString('Redis ', $status['title']);
    }

    /**
     * The colour rule, across every combination that matters.
     *
     * Tested through the pure `colorFor()` rather than `current()`,
     * because `current()` reads the live database and Redis — so it can
     * only ever assert whatever this machine is doing right now, which
     * is normally the healthy case. The unhealthy ones are the whole
     * point and are unreachable that way.
     *
     * An earlier version of this test did exactly that and asserted
     * that healthy infrastructure implies a label other than "Down".
     * It failed, correctly: this installation's health score is
     * critical (96% memory, 94% disk) with the database and Redis both
     * up. "Down" was the right answer and the assertion was wrong —
     * there are two independent routes to danger, and that test only
     * knew about one.
     */
    #[DataProvider('colourCases')]
    public function test_the_colour_rule(bool $database, bool $redis, string $label, string $expected): void
    {
        $this->assertSame(
            $expected,
            PlatformStatusBadgeService::colorFor($database, $redis, $label),
            "db={$database}, redis={$redis}, label={$label}",
        );
    }

    /**
     * @return array<string, array{bool, bool, string, string}>
     */
    public static function colourCases(): array
    {
        return [
            // Infrastructure down outranks everything, including a
            // health label that still reads Excellent — the score is
            // derived from data we may have failed to read.
            'database down beats an excellent score' => [false, true, 'Excellent', 'danger'],
            'redis down beats an excellent score' => [true, false, 'Excellent', 'danger'],
            'both down' => [false, false, 'Critical', 'danger'],

            'everything healthy' => [true, true, 'Excellent', 'success'],
            'good is still operational' => [true, true, 'Good', 'success'],
            'needs attention is degraded' => [true, true, 'Needs Attention', 'warning'],

            // The case the first version of this test got wrong: the
            // dependencies are reachable and the platform is still in
            // trouble.
            'critical score with healthy infrastructure' => [true, true, 'Critical', 'danger'],
            'an unrecognised label fails loud, not quiet' => [true, true, 'Whatever', 'danger'],
        ];
    }

    /**
     * A Redis that nothing uses is not an outage.
     *
     * Production runs on shared hosting with no Redis at all — cache,
     * session and queue are on the database by design. The badge used to
     * ping Redis unconditionally, fail, and render a red "Down" beside a
     * health score reading "Good". It said Down for the platform's entire
     * first day online while nothing was wrong.
     *
     * An indicator that is always red is worse than none: the first real
     * outage looks exactly like every day before it. So the check is now
     * "is the dependency satisfied", and where there is no dependency the
     * answer is yes.
     */
    public function test_an_unused_redis_does_not_make_the_platform_look_down(): void
    {
        config([
            'cache.default' => 'database',
            'session.driver' => 'database',
            'queue.default' => 'database',
            'broadcasting.default' => 'null',
        ]);

        app(PlatformStatusBadgeService::class)->flush();

        $status = app(PlatformStatusBadgeService::class)->current();

        $this->assertFalse($status['redisInUse']);
        $this->assertTrue($status['redis'], 'An unconfigured Redis must not read as a failed dependency.');
        $this->assertStringContainsString('Redis not in use', $status['title']);
        $this->assertNotSame('Down', $status['label']);
    }

    /**
     * The other half of the same rule, and the reason it is written
     * against configuration rather than reachability: switch the cache
     * back to Redis and an unreachable Redis is an outage again.
     */
    public function test_a_configured_but_unreachable_redis_is_still_an_outage(): void
    {
        config([
            'cache.default' => 'redis',
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 6399, // Nothing listens here.
        ]);

        app(PlatformStatusBadgeService::class)->flush();

        $status = app(PlatformStatusBadgeService::class)->current();

        $this->assertTrue($status['redisInUse']);
        $this->assertFalse($status['redis']);
        $this->assertSame('Down', $status['label']);
    }

    public function test_the_live_badge_agrees_with_the_rule(): void
    {
        $status = app(PlatformStatusBadgeService::class)->current();

        $this->assertSame(
            PlatformStatusBadgeService::colorFor(
                $status['database'],
                $status['redis'],
                $status['healthLabel'],
            ),
            $status['color'],
        );
    }

    /**
     * The badge costs a database round trip and a Redis ping, and this
     * middleware runs on every tenant request too — so it must not be
     * computed for anyone who will never see it.
     */
    public function test_it_is_not_computed_for_tenant_users(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->where('platformAuth.status', null)
            );
    }
}
