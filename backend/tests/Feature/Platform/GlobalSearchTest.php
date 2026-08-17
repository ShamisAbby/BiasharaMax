<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_it_finds_a_business_by_name(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($platformUser, 'platform')
            ->getJson(route('platform.search', ['q' => substr($business->name, 0, 5)]));

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'Business', 'title' => $business->name]);
    }

    public function test_it_finds_a_platform_admin_by_email(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $other = PlatformUser::factory()->create(['email' => 'findme@example.com']);

        $response = $this->actingAs($platformUser, 'platform')
            ->getJson(route('platform.search', ['q' => 'findme']));

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'Platform Admin', 'id' => $other->id]);
    }

    public function test_it_returns_no_results_for_a_query_shorter_than_two_characters(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $response = $this->actingAs($platformUser, 'platform')
            ->getJson(route('platform.search', ['q' => 'a']));

        $response->assertOk();
        $response->assertExactJson(['results' => []]);
    }

    public function test_guest_cannot_search(): void
    {
        $this->getJson(route('platform.search', ['q' => 'test']))->assertUnauthorized();
    }
}
