<?php

namespace Tests\Feature\Api;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Product;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * The product pull pages, and paging is where sync quietly loses data.
 *
 * Two failures are covered here because both are silent — nothing errors,
 * nothing retries, the till just ends up with a catalog missing rows
 * nobody can explain:
 *
 *  1. The cursor has to be able to resume at all.
 *  2. It has to resume *unambiguously*. Ordering by `updated_at` alone and
 *     asking for "greater than the last row's timestamp" drops every row
 *     that shares that timestamp — and a bulk import writes hundreds of
 *     rows within the same second, so this is the normal case for a first
 *     sync, not an exotic one.
 */
class SyncPaginationTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private const PAGE_SIZE = 500;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_a_catalog_larger_than_one_page_can_be_pulled_completely(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        // One row over a page, all sharing a timestamp — exactly what an
        // import produces, and exactly what a timestamp-only cursor loses.
        $this->seedProducts($business->getKey(), self::PAGE_SIZE + 1, Carbon::parse('2026-01-01 09:00:00'));

        $token = $this->loginViaApi($owner);
        $seen = [];
        $since = null;
        $sinceId = null;

        // The same loop the client runs. If the cursor is wrong this either
        // spins forever or stops early — both caught below.
        for ($page = 0; $page < 10; $page++) {
            $query = array_filter([
                'since' => $since,
                'since_id' => $sinceId,
            ]);

            $response = $this->withToken($token)
                ->getJson('/api/v1/sync/products?'.http_build_query($query));

            $response->assertOk();

            foreach ($response->json('data') as $row) {
                $seen[] = $row['id'];
            }

            if ($response->json('has_more') !== true) {
                break;
            }

            $since = $response->json('next_since');
            $sinceId = $response->json('next_since_id');

            $this->assertNotNull($since, 'A page reporting has_more must say where to resume.');
            $this->assertNotNull($sinceId, 'Resuming needs the id tiebreaker, not just a timestamp.');
        }

        $this->assertCount(
            self::PAGE_SIZE + 1,
            array_unique($seen),
            'Every product must arrive exactly once across the paged pull.',
        );
    }

    public function test_the_final_page_reports_no_more_and_no_cursor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->seedProducts($business->getKey(), 3, Carbon::parse('2026-01-01 09:00:00'));

        $response = $this->withToken($this->loginViaApi($owner))
            ->getJson('/api/v1/sync/products');

        $response->assertOk();
        $response->assertJsonPath('has_more', false);

        // Null on the last page so the client knows to store `server_time`
        // as its watermark instead of a row cursor — the two mean different
        // things, and only server_time also covers later deletions.
        $response->assertJsonPath('next_since', null);
        $response->assertJsonPath('next_since_id', null);
    }

    public function test_an_incremental_pull_returns_only_what_changed(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->seedProducts($business->getKey(), 2, Carbon::parse('2026-01-01 09:00:00'));

        $token = $this->loginViaApi($owner);
        $first = $this->withToken($token)->getJson('/api/v1/sync/products');
        $watermark = $first->json('server_time');

        $this->assertCount(2, $first->json('data'));

        // "Later" has to mean later than the *watermark*, which is the
        // clock, not later than the previously seeded rows. An earlier
        // version of this test seeded a 2026-02-01 row, called it later
        // than the 2026-01-01 ones, and asserted it came back — it never
        // could, because the watermark is `now` and 2026-02-01 is in the
        // past. Moving the clock is what makes the intent unambiguous.
        $this->travel(2)->minutes();
        $this->seedProducts($business->getKey(), 1, Carbon::now(), 'Later');

        $second = $this->withToken($token)
            ->getJson('/api/v1/sync/products?'.http_build_query(['since' => $watermark]));

        $second->assertOk();
        $this->assertCount(1, $second->json('data'));
        $this->assertSame('Later 1', $second->json('data.0.name'));
    }

    /**
     * The watermark boundary, which is where the third silent loss lived.
     *
     * `updated_at` is second-granular and `since` is applied with a strict
     * `>`. So a product edited in the same second the pull ran is in
     * neither bucket — too late for this response, excluded from the next
     * one by the watermark this response handed back. Gone, permanently,
     * with nothing logged and nothing to retry.
     *
     * The window is only about a second wide, which is exactly what makes
     * it worth a test: too narrow to reproduce by hand, wide enough to hit
     * a busy shop, and indistinguishable afterwards from "someone forgot
     * to save".
     */
    public function test_a_product_changed_while_the_pull_ran_is_not_lost(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->seedProducts($business->getKey(), 1, Carbon::parse('2026-01-01 09:00:00'));

        $token = $this->loginViaApi($owner);
        $watermark = $this->withToken($token)
            ->getJson('/api/v1/sync/products')
            ->json('server_time');

        // Same second as the pull. No time travel, on purpose — this is
        // the real clock, which is the whole scenario.
        $this->seedProducts($business->getKey(), 1, Carbon::now(), 'Concurrent');

        $second = $this->withToken($token)
            ->getJson('/api/v1/sync/products?'.http_build_query(['since' => $watermark]));

        $second->assertOk();

        $this->assertContains(
            'Concurrent 1',
            array_column($second->json('data'), 'name'),
            'A row written in the same second as the watermark must survive to the next pull.',
        );
    }

    // ---------------------------------------------------------------

    private function loginViaApi(User $user): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'test-device',
        ]);

        $response->assertOk();

        return $response->json('token');
    }

    /**
     * Inserted directly with an identical `updated_at`, which is the whole
     * point — going through the model would stamp each row a moment apart
     * and hide the collision this is meant to expose.
     */
    private function seedProducts(string $businessId, int $count, Carbon $updatedAt, string $prefix = 'Product'): void
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'business_id' => $businessId,
                'name' => "{$prefix} {$i}",
                'slug' => Str::slug("{$prefix} {$i}").'-'.Str::random(6),
                'sku' => strtoupper(Str::random(10)),
                'product_type' => 'simple',
                'status' => 'active',
                'created_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Product::query()->insert($chunk);
        }
    }
}
