<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_matrix_lists_permissions_of_both_scopes(): void
    {
        $platformUser = PlatformUser::factory()->create();

        // Counted from the seeder rather than hardcoded. This asserted a
        // literal 2, which broke the moment the platform role catalogue
        // was expanded — the test's actual subject is "the matrix lists
        // every platform role", not "there are exactly two of them".
        $expected = PlatformRole::query()->count();

        $this->assertGreaterThan(1, $expected, 'Expected the platform role seeder to have run.');

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.rbac.permission-matrix.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Rbac/PermissionMatrix/Index')
                ->has('platformRoles', $expected)
            );
    }

    public function test_matrix_can_be_filtered_by_scope(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $response = $this->actingAs($platformUser, 'platform')
            ->get(route('platform.rbac.permission-matrix.index', ['scope' => 'platform']));

        $response->assertOk();
        $permissions = $response->viewData('page')['props']['permissions'];
        $this->assertNotEmpty($permissions);
        foreach ($permissions as $permission) {
            $this->assertSame('platform', $permission['scope']);
        }
    }

    public function test_matrix_can_be_searched(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $response = $this->actingAs($platformUser, 'platform')
            ->get(route('platform.rbac.permission-matrix.index', ['search' => 'business types']));

        $permissions = $response->viewData('page')['props']['permissions'];
        $this->assertNotEmpty($permissions);
        foreach ($permissions as $permission) {
            $this->assertStringContainsString('business_types', $permission['slug']);
        }
    }
}
