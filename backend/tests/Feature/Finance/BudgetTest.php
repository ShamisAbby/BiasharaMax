<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\Budget;
use App\Domain\Finance\Services\BudgetService;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\JournalPostingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function seedAndGetAccounts(string $businessId): array
    {
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $income = Account::query()->where('business_id', $businessId)->where('code', '4000')->firstOrFail();
        $expense = Account::query()->where('business_id', $businessId)->where('code', '5000')->firstOrFail();

        return [$income, $expense];
    }

    public function test_can_create_a_budget_with_lines(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$income, $expense] = $this->seedAndGetAccounts($business->id);

        $service = app(BudgetService::class);

        $budget = $service->create($business->id, [
            'name' => 'FY 2026 Budget',
            'fiscal_year' => 2026,
            'created_by' => $owner->id,
        ], [
            ['account_id' => $income->id, 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'budgeted_amount' => '500000.00'],
            ['account_id' => $expense->id, 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'budgeted_amount' => '300000.00'],
        ]);

        $this->assertEquals('draft', $budget->status);
        $this->assertEquals('FY 2026 Budget', $budget->name);
        $this->assertCount(2, $budget->lines);
    }

    public function test_draft_budget_can_be_approved(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$income] = $this->seedAndGetAccounts($business->id);

        $service = app(BudgetService::class);

        $budget = $service->create($business->id, [
            'name' => 'Test Budget',
            'fiscal_year' => 2026,
            'created_by' => $owner->id,
        ], [
            ['account_id' => $income->id, 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'budgeted_amount' => '100000.00'],
        ]);

        $approved = $service->approve($budget, $owner->id);

        $this->assertEquals('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertEquals($owner->id, $approved->approved_by);
    }

    public function test_approving_non_draft_budget_throws(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$income] = $this->seedAndGetAccounts($business->id);

        $service = app(BudgetService::class);

        $budget = $service->create($business->id, [
            'name' => 'Test Budget',
            'fiscal_year' => 2026,
            'created_by' => $owner->id,
        ], [
            ['account_id' => $income->id, 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'budgeted_amount' => '100000.00'],
        ]);

        $service->approve($budget, $owner->id);

        $this->expectException(\RuntimeException::class);
        $service->approve($budget->fresh(), $owner->id);
    }

    public function test_activating_archives_previous_active_budget(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$income] = $this->seedAndGetAccounts($business->id);

        $service = app(BudgetService::class);

        $firstBudget = $service->create($business->id, [
            'name' => 'First Budget',
            'fiscal_year' => 2026,
            'created_by' => $owner->id,
        ], [
            ['account_id' => $income->id, 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'budgeted_amount' => '100000.00'],
        ]);
        $service->approve($firstBudget, $owner->id);
        $service->activate($firstBudget->fresh());

        $secondBudget = $service->create($business->id, [
            'name' => 'Second Budget',
            'fiscal_year' => 2026,
            'created_by' => $owner->id,
        ], [
            ['account_id' => $income->id, 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'budgeted_amount' => '200000.00'],
        ]);
        $service->approve($secondBudget, $owner->id);
        $service->activate($secondBudget->fresh());

        $this->assertEquals('archived', $firstBudget->fresh()->status);
        $this->assertEquals('active', $secondBudget->fresh()->status);
    }

    public function test_budget_vs_actual_returns_correct_variance(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$income, $expense] = $this->seedAndGetAccounts($business->id);

        // Post an actual transaction
        $cash = Account::query()->where('business_id', $business->id)->where('code', '1000')->firstOrFail();

        app(JournalPostingService::class)->postImmediately($business->id, [
            'entry_date' => '2026-06-15',
            'type' => 'manual',
            'description' => 'Actual revenue',
        ], [
            ['account_id' => $cash->id, 'debit' => '750.00', 'credit' => '0', 'description' => ''],
            ['account_id' => $income->id, 'debit' => '0', 'credit' => '750.00', 'description' => ''],
        ], $owner->id);

        $service = app(BudgetService::class);

        $budget = $service->create($business->id, [
            'name' => 'Test Budget',
            'fiscal_year' => 2026,
            'created_by' => $owner->id,
        ], [
            ['account_id' => $income->id, 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'budgeted_amount' => '1000.00'],
        ]);

        $vsActual = $service->budgetVsActual($budget);

        $this->assertCount(1, $vsActual);
        $this->assertEquals('1000.00', $vsActual[0]['budgeted']);
        $this->assertEquals('750.00', $vsActual[0]['actual']);
        // Variance = actual - budgeted = 750 - 1000 = -250 (under budget)
        $this->assertEquals('-250.00', $vsActual[0]['variance']);
    }
}
