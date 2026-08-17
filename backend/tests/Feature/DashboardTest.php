<?php

namespace Tests\Feature;

use App\Domain\Authentication\Models\User;
use App\Domain\RBAC\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_sees_real_inventory_and_sales_summary_on_the_dashboard(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Dashboard')
                ->where('inventory.total_products', 0)
                ->where('inventory.health_score', 100)
                ->where('sales.summary.today_sales_count', 0)
                ->where('sales.summary.today_revenue', 0)
                ->where('sales.summary.customers_count', 0)
                ->has('sales.paymentMethods', 0)
                ->where('businessHealth.score', 100)
                ->where('businessHealth.status', 'Excellent')
                ->where('financials.cash_balance', 0)
                ->where('financials.today_expenses', 0)
                ->where('crm.total_customers', 0)
                ->where('businessPulse.inventory_health.score', 100)
                ->where('businessPulse.cash_flow.status', 'healthy')
                ->has('recentActivity')
            );
    }

    public function test_user_without_inventory_permission_sees_no_inventory_data(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $noAccessRole = Role::query()->create([
            'business_id' => $business->getKey(),
            'name' => 'No Access',
            'slug' => 'no-access',
            'is_system' => false,
        ]);
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $noAccessRole->id,
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Dashboard')
                ->where('inventory', null)
                ->where('sales', null)
                ->where('financials', null)
                ->where('crm', null)
                ->where('businessPulse', null)
            );
    }

    public function test_business_pulse_reflects_real_revenue_and_profit_growth(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = \App\Domain\Business\Models\Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = \App\Domain\Business\Models\Warehouse::query()->where('business_id', $business->id)->firstOrFail();

        $product = \App\Domain\Inventory\Models\Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        \App\Domain\Inventory\Models\Inventory::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'quantity' => 10,
        ]);

        $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['amount' => 2000, 'payment_method' => 'cash']],
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->where('businessPulse.revenue_growth.this_week', 2000)
                ->where('businessPulse.profit_trend.this_week', 800)
                ->where('businessPulse.revenue_growth.percent', 100)
                ->where('sales.recentOrders.0.total_amount', 2000)
                ->where('sales.recentOrders.0.payment_status', 'paid')
                ->where('branchPerformance', [])
            );
    }

    public function test_low_stock_products_and_branch_performance_are_real(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = \App\Domain\Business\Models\Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = \App\Domain\Business\Models\Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $secondBranch = \App\Domain\Business\Models\Branch::create(['business_id' => $business->id, 'name' => 'North Branch', 'code' => 'NB1']);

        $product = \App\Domain\Inventory\Models\Product::create([
            'business_id' => $business->id, 'name' => 'Low Stock Widget', 'slug' => 'lsw-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        \App\Domain\Inventory\Models\Inventory::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'quantity' => 2, 'reorder_level' => 10,
        ]);

        $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['amount' => 1000, 'payment_method' => 'cash']],
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->where('lowStockProducts.0.name', 'Low Stock Widget')
                ->where('lowStockProducts.0.quantity', 1)
                ->has('branchPerformance', 2)
                ->where('branchPerformance.0.name', $branch->name)
            );

        $this->assertNotNull($secondBranch);
    }
}
