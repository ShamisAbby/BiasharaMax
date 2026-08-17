<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Finance\Models\PaymentTransaction;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class FinanceReportControllerTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_the_reports_index_with_a_default_report(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.reports.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Finance/Reports/Index')
                ->where('selectedReport', 'revenue')
                ->has('catalog')
            );
    }

    public function test_platform_user_can_switch_reports_via_query_param(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.reports.index', ['report' => 'refunds']))
            ->assertInertia(fn (AssertInertia $page) => $page->where('selectedReport', 'refunds'));
    }

    public function test_csv_export_downloads_real_data(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        PaymentTransaction::factory()->create(['business_id' => $business->id, 'amount' => 99000, 'status' => PaymentTransaction::STATUS_SUCCESSFUL, 'paid_at' => now()]);

        $response = $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.reports.export.csv', ['report' => 'revenue']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('99000', $response->streamedContent());
    }

    public function test_pdf_export_returns_a_valid_pdf(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $response = $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.reports.export.pdf', ['report' => 'revenue']));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_finance_dashboard_renders_with_real_aggregates(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [, $business] = $this->createOwnerWithBusiness();
        PaymentTransaction::factory()->create(['business_id' => $business->id, 'amount' => 30000, 'status' => PaymentTransaction::STATUS_SUCCESSFUL, 'paid_at' => now()]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.finance.dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Finance/Dashboard')
                ->where('revenue.total', 30000)
            );
    }

    public function test_tenant_user_cannot_access_reports(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.finance.reports.index'))
            ->assertRedirect(route('platform.login'));
    }
}
