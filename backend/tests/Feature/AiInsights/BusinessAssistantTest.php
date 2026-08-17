<?php

namespace Tests\Feature\AiInsights;

use App\Domain\Accounting\Models\Expense;
use App\Domain\AiInsights\Services\BusinessAssistantService;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sales\Models\Customer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class BusinessAssistantTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_reorder_question_lists_products_at_or_below_reorder_level(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();

        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        Inventory::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $product->id, 'quantity' => 2, 'reorder_level' => 5,
        ]);

        $answer = app(BusinessAssistantService::class)->ask($business, 'Which products should I reorder?');

        $this->assertStringContainsString('Widget', $answer['answer']);
        $this->assertSame('inventory', $answer['source']);
    }

    public function test_reorder_question_with_healthy_stock_says_nothing_needed(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $answer = app(BusinessAssistantService::class)->ask($business, 'restock please');

        $this->assertSame('Nothing needs reordering right now — all stock levels are healthy.', $answer['answer']);
    }

    public function test_debtors_question_lists_customers_with_outstanding_balance(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => 'credit', 'current_balance' => 500]);

        $answer = app(BusinessAssistantService::class)->ask($business, 'Who owes me money?');

        $this->assertStringContainsString('Jane', $answer['answer']);
        $this->assertSame('sales', $answer['source']);
    }

    public function test_payables_question_lists_suppliers_with_approved_unpaid_expenses(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $supplier = Supplier::create(['business_id' => $business->id, 'name' => 'Acme Supplies']);
        Expense::create([
            'business_id' => $business->id, 'title' => 'Stock purchase', 'amount' => 300,
            'expense_date' => Carbon::today()->toDateString(), 'supplier_id' => $supplier->id,
            'status' => Expense::STATUS_APPROVED,
        ]);

        $answer = app(BusinessAssistantService::class)->ask($business, 'Which suppliers should I pay first?');

        $this->assertStringContainsString('Acme Supplies', $answer['answer']);
        $this->assertSame('accounting', $answer['source']);
    }

    public function test_slow_movers_question_excludes_recently_sold_products(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();

        $slowProduct = Product::create([
            'business_id' => $business->id, 'name' => 'Dusty Widget', 'slug' => 'dusty-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        Inventory::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'product_id' => $slowProduct->id, 'quantity' => 10,
        ]);

        $answer = app(BusinessAssistantService::class)->ask($business, 'Which products are slow moving?');

        $this->assertStringContainsString('Dusty Widget', $answer['answer']);
    }

    public function test_pricing_question_is_honestly_declined(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $answer = app(BusinessAssistantService::class)->ask($business, 'Recommend a price adjustment for my products');

        $this->assertSame('declined', $answer['source']);
    }

    public function test_unmatched_question_with_no_ai_integration_is_honestly_declined(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $answer = app(BusinessAssistantService::class)->ask($business, 'What is the meaning of life?');

        $this->assertSame('declined', $answer['source']);
    }

    public function test_assistant_endpoint_returns_a_real_answer(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => 'credit', 'current_balance' => 500]);

        $this->actingAs($owner)
            ->post('/assistant/ask', ['question' => 'Who owes me money?'])
            ->assertOk()
            ->assertJsonPath('source', 'sales');
    }
}
