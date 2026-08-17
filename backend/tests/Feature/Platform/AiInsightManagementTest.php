<?php

namespace Tests\Feature\Platform;

use App\Domain\AiInsights\Models\AiInsight;
use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Integrations\Models\Integration;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class AiInsightManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_can_view_ai_insights_index(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.ai-insights.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/System/AiInsights/Index')
                ->has('revenueForecast')
                ->has('churnRisk')
                ->where('aiConfigured', false)
            );
    }

    public function test_generating_a_narrative_fails_honestly_when_no_ai_provider_is_configured(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.ai-insights.generate-narrative'), ['type' => AiInsight::TYPE_REVENUE_FORECAST])
            ->assertSessionHasErrors(['ai']);

        $this->assertDatabaseCount('ai_insights', 0);
    }

    public function test_generating_a_narrative_succeeds_when_an_ai_provider_is_configured_and_faked(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Revenue is expected to grow next month.']]],
            ], 200),
        ]);

        Integration::factory()->create([
            'category' => Integration::CATEGORY_AI,
            'provider' => 'openai',
            'is_enabled' => true,
            'credentials' => ['api_key' => 'sk-test'],
        ]);

        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.ai-insights.generate-narrative'), ['type' => AiInsight::TYPE_REVENUE_FORECAST])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ai_insights', [
            'type' => AiInsight::TYPE_REVENUE_FORECAST,
            'summary' => 'Revenue is expected to grow next month.',
        ]);
    }

    public function test_platform_user_can_mark_an_insight_as_read(): void
    {
        $platformUser = PlatformUser::factory()->create();
        $insight = AiInsight::query()->create([
            'type' => AiInsight::TYPE_CHURN_RISK,
            'title' => 'Churn Risk',
            'summary' => 'Some businesses are at risk.',
            'data' => [],
            'is_read' => false,
        ]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.system.ai-insights.mark-read', $insight->id))
            ->assertSessionHasNoErrors();

        $this->assertTrue($insight->fresh()->is_read);
    }

    public function test_platform_user_without_view_permission_cannot_view_ai_insights(): void
    {
        $role = PlatformRole::query()->create(['name' => 'No AI', 'slug' => 'no-ai-insights', 'is_system' => false]);
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $role->id]);

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.system.ai-insights.index'))
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_ai_insights(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.system.ai-insights.index'))
            ->assertRedirect(route('platform.login'));
    }
}
