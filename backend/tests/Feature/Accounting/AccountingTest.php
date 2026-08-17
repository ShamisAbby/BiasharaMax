<?php

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\ExpenseCategory;
use App\Domain\Accounting\Models\Income;
use App\Domain\Accounting\Services\FinancialReportService;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\RBAC\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_an_expense_category(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/accounting/expense-categories', [
            'name' => 'Rent',
            'description' => 'Monthly office rent',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expense_categories', [
            'business_id' => $business->id,
            'name' => 'Rent',
            'slug' => 'rent',
        ]);
    }

    public function test_owner_can_create_an_expense_with_default_pending_status(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $category = ExpenseCategory::create(['business_id' => $business->id, 'name' => 'Utilities', 'slug' => 'utilities']);

        $this->actingAs($owner)->post('/accounting/expenses', [
            'expense_category_id' => $category->id,
            'title' => 'Electricity bill',
            'amount' => 150.50,
            'expense_date' => Carbon::today()->toDateString(),
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $expense = Expense::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame(Expense::STATUS_PENDING, $expense->status);
        $this->assertSame('150.50', $expense->amount);
    }

    public function test_owner_can_approve_a_pending_expense(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $expense = Expense::create([
            'business_id' => $business->id, 'title' => 'Fuel', 'amount' => 80,
            'expense_date' => Carbon::today()->toDateString(),
        ]);

        $this->actingAs($owner)->post("/accounting/expenses/{$expense->id}/approve")
            ->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame(Expense::STATUS_APPROVED, $expense->status);
        $this->assertSame($owner->id, $expense->approved_by);
    }

    public function test_rejecting_an_expense_requires_a_reason(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $expense = Expense::create([
            'business_id' => $business->id, 'title' => 'Fuel', 'amount' => 80,
            'expense_date' => Carbon::today()->toDateString(),
        ]);

        $this->actingAs($owner)->post("/accounting/expenses/{$expense->id}/reject", [])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($owner)->post("/accounting/expenses/{$expense->id}/reject", [
            'rejection_reason' => 'Missing receipt',
        ])->assertSessionHasNoErrors();

        $this->assertSame(Expense::STATUS_REJECTED, $expense->refresh()->status);
        $this->assertSame('Missing receipt', $expense->rejection_reason);
    }

    public function test_only_an_approved_expense_can_be_marked_paid(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $expense = Expense::create([
            'business_id' => $business->id, 'title' => 'Fuel', 'amount' => 80,
            'expense_date' => Carbon::today()->toDateString(), 'status' => Expense::STATUS_APPROVED,
        ]);

        $this->actingAs($owner)->post("/accounting/expenses/{$expense->id}/mark-paid")
            ->assertSessionHasNoErrors();

        $this->assertSame(Expense::STATUS_PAID, $expense->refresh()->status);
    }

    public function test_owner_can_create_a_manual_income_entry(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/accounting/income', [
            'title' => 'Consulting fee',
            'category' => 'service',
            'amount' => 500,
            'income_date' => Carbon::today()->toDateString(),
            'payment_method' => 'bank_transfer',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('incomes', [
            'business_id' => $business->id,
            'title' => 'Consulting fee',
            'category' => Income::CATEGORY_SERVICE,
        ]);
    }

    public function test_employee_without_accounting_permission_cannot_create_an_expense(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post('/accounting/expenses', [
            'title' => 'Fuel',
            'amount' => 80,
            'expense_date' => Carbon::today()->toDateString(),
            'payment_method' => 'cash',
        ])->assertForbidden();

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_employee_without_approve_permission_cannot_approve_an_expense(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $expense = Expense::create([
            'business_id' => $business->id, 'title' => 'Fuel', 'amount' => 80,
            'expense_date' => Carbon::today()->toDateString(),
        ]);

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post("/accounting/expenses/{$expense->id}/approve")
            ->assertForbidden();

        $this->assertSame(Expense::STATUS_PENDING, $expense->refresh()->status);
    }

    public function test_financial_report_summary_combines_real_sales_expense_and_income_data(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();

        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 10]);

        $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['amount' => 2000, 'payment_method' => 'cash']],
        ])->assertSessionHasNoErrors();

        Income::create([
            'business_id' => $business->id, 'title' => 'Consulting', 'category' => Income::CATEGORY_SERVICE,
            'amount' => 300, 'income_date' => Carbon::today()->toDateString(), 'payment_method' => 'cash',
        ]);

        Expense::create([
            'business_id' => $business->id, 'title' => 'Fuel', 'amount' => 200,
            'expense_date' => Carbon::today()->toDateString(), 'payment_method' => 'cash', 'status' => Expense::STATUS_PAID,
        ]);

        $summary = app(FinancialReportService::class)->summary($business->id);

        // Sales profit = (1000-600)*2 = 800; + other income 300 = gross profit 1100; net = 1100-200 = 900.
        $this->assertSame(800.0 + 300.0, $summary['gross_profit']);
        $this->assertSame(900.0, $summary['net_profit']);
        $this->assertSame(2000.0 + 300.0, $summary['total_revenue']);
        // Cash balance = sale payment (2000) + income (300) - paid expense (200) = 2100.
        $this->assertSame(2100.0, $summary['cash_balance']);
    }
}
