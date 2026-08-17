<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_log_in_with_valid_credentials(): void
    {
        $platformUser = PlatformUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('platform.login'), [
            'email' => $platformUser->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticatedAs($platformUser, 'platform');
    }

    public function test_superadmin_cannot_log_in_with_invalid_credentials(): void
    {
        $platformUser = PlatformUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->from(route('platform.login'))->post(route('platform.login'), [
            'email' => $platformUser->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_tenant_user_credentials_do_not_grant_platform_access(): void
    {
        $tenantUser = \App\Domain\Authentication\Models\User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->from(route('platform.login'))->post(route('platform.login'), [
            'email' => $tenantUser->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_superadmin_can_log_out(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.logout'))
            ->assertRedirect(route('platform.login'));

        $this->assertGuest('platform');
    }

    public function test_guest_cannot_view_the_platform_dashboard(): void
    {
        $this->get(route('platform.dashboard'))
            ->assertRedirect(route('platform.login'));
    }
}
