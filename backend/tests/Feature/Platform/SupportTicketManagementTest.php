<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Support\Models\SupportAgent;
use App\Domain\Support\Models\SupportTicket;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SupportTicketManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_tickets_index(): void
    {
        $platformUser = PlatformUser::factory()->create();
        SupportTicket::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.operations.support.index'))
            ->assertOk();
    }

    public function test_platform_user_can_reply_and_first_response_is_recorded(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.support.reply', $ticket->id), ['body' => 'We are on it.'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($ticket->fresh()->first_response_at);
        $this->assertSame(1, $ticket->messages()->count());
    }

    public function test_internal_notes_are_flagged_and_not_counted_as_first_response(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.operations.support.reply', $ticket->id), [
            'body' => 'Internal note only',
            'is_internal_note' => true,
        ]);

        $this->assertNull($ticket->fresh()->first_response_at);
        $this->assertTrue($ticket->messages()->first()->is_internal_note);
    }

    public function test_assigning_a_ticket_sets_status_in_progress(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $agentUser = PlatformUser::factory()->create();
        $agent = SupportAgent::create(['platform_user_id' => $agentUser->id]);
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.support.assign', $ticket->id), ['agent_id' => $agent->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($agent->id, $ticket->fresh()->assigned_agent_id);
        $this->assertSame(SupportTicket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
    }

    public function test_resolve_close_and_reopen_transitions(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($platformUser, 'platform')->post(route('platform.operations.support.resolve', $ticket->id));
        $this->assertSame(SupportTicket::STATUS_RESOLVED, $ticket->fresh()->status);

        $this->actingAs($platformUser, 'platform')->post(route('platform.operations.support.close', $ticket->id));
        $this->assertSame(SupportTicket::STATUS_CLOSED, $ticket->fresh()->status);

        $this->actingAs($platformUser, 'platform')->post(route('platform.operations.support.reopen', $ticket->id));
        $this->assertSame(SupportTicket::STATUS_REOPENED, $ticket->fresh()->status);
    }

    public function test_platform_admin_without_manage_permission_cannot_assign(): void
    {
        $role = \App\Domain\RBAC\Models\PlatformRole::query()->create(['name' => 'Viewer', 'slug' => 'support-viewer', 'is_system' => false]);
        $role->permissions()->sync(
            \App\Domain\RBAC\Models\Permission::query()->where('slug', 'support.view')->pluck('id'),
        );
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.operations.support.resolve', $ticket->id))
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_support(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.operations.support.index'))
            ->assertRedirect(route('platform.login'));
    }
}
