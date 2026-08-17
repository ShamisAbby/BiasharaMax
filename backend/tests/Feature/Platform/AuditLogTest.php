<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_creating_a_business_writes_a_real_audit_log_entry(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $business::class,
            'auditable_id' => $business->id,
            'action' => 'created',
        ]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.audit-logs.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/AuditLogs/Index')
                ->where('logs.meta.total', fn (int $total) => $total > 0)
            );

        $this->assertNotNull($owner);
    }

    public function test_filtering_audit_logs_by_action(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.audit-logs.index', ['action' => 'created']))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->where('logs.data.0.action', 'created')
            );
    }

    public function test_tenant_user_cannot_access_audit_logs(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.audit-logs.index'))
            ->assertRedirect(route('platform.login'));
    }

    public function test_audit_log_captures_module_and_a_default_normal_risk_level(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $business::class,
            'action' => 'created',
            'module' => 'Business',
            'risk_level' => 'normal',
        ]);
    }

    public function test_deleting_a_tenant_role_is_flagged_high_risk(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $role = \App\Domain\RBAC\Models\Role::query()->create([
            'business_id' => $business->id, 'name' => 'Temp', 'slug' => 'temp-role', 'is_system' => false,
        ]);
        $roleId = $role->id;
        $role->delete();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $roleId,
            'action' => 'deleted',
            'risk_level' => 'high',
        ]);
    }

    public function test_updating_a_tenant_role_is_flagged_elevated_risk(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $role = \App\Domain\RBAC\Models\Role::query()->create([
            'business_id' => $business->id, 'name' => 'Temp', 'slug' => 'temp-role-2', 'is_system' => false,
        ]);
        $role->update(['description' => 'Updated description']);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $role->id,
            'action' => 'updated',
            'risk_level' => 'elevated',
        ]);
    }

    public function test_filtering_audit_logs_by_risk_level(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.audit-logs.index', ['risk_level' => 'normal']))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->where('logs.data.0.risk_level', 'normal')
            );
    }

    public function test_export_downloads_a_csv_with_real_entries(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($platformUser, 'platform')->get(route('platform.audit-logs.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($business->name, $response->streamedContent());
    }
}
