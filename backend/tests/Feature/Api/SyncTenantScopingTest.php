<?php

namespace Tests\Feature\Api;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Product;
use App\Domain\Sales\Models\Sale;
use App\Domain\Shared\Models\AuditLog;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Proves that the `sanctum` guard check in BelongsToTenant / HasUserstamps /
 * Auditable (app/Domain/Shared/Concerns/) actually does what their
 * docblocks claim: a Sanctum-authenticated request (Flutter Desktop today)
 * is tenant-scoped exactly like a `web` session request, and every record
 * it creates carries a real actor. Before those traits checked the
 * `sanctum` guard, a token-authenticated request would either bypass the
 * BelongsToTenant scope entirely (cross-tenant leak) or stamp
 * created_by/updated_by and the audit log's actor with null.
 */
class SyncTenantScopingTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function loginViaApi(User $user): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'test-device',
        ]);

        $response->assertOk();

        return $response->json('token');
    }

    private function createProduct(User $owner, string $name): void
    {
        $this->actingAs($owner)->post('/inventory/products', [
            'name' => $name,
            'sku' => null,
            'product_type' => 'simple',
            'cost_price' => 100,
            'selling_price' => 150,
            'status' => 'active',
            'visibility' => 'visible',
            'track_stock' => false,
        ])->assertSessionHasNoErrors();
    }

    public function test_desktop_token_only_pulls_its_own_businesss_products(): void
    {
        [$ownerA] = $this->createOwnerWithBusiness();
        [$ownerB] = $this->createOwnerWithBusiness();

        $this->createProduct($ownerA, 'Business A Product');
        $this->createProduct($ownerB, 'Business B Product');

        $token = $this->loginViaApi($ownerA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/sync/products');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Business A Product'));
        $this->assertFalse($names->contains('Business B Product'));
    }

    public function test_sale_created_via_desktop_sync_records_a_real_actor_not_null(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->createProduct($owner, 'Synced Product');

        $product = Product::query()->where('name', 'Synced Product')->firstOrFail();
        $branch = $business->branches()->first();
        $warehouse = $branch->warehouses()->first();

        $token = $this->loginViaApi($owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/sync/sales', [
            'sales' => [[
                'idempotency_key' => 'test-key-1',
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                // Full payment (product's selling_price is 150, set by
                // createProduct() above) — a walk-in cash sale with no
                // customer_id and a nonzero balance_due would hit
                // SaleService::assertCreditSaleAllowed()'s "customer
                // required to carry a balance" rule and get rejected, which
                // isn't what this test is proving (it's proving actor/audit
                // resolution over a Sanctum-authenticated sync request, not
                // credit-sale business rules).
                'payments' => [
                    ['amount' => 150, 'payment_method' => 'cash'],
                ],
            ]],
        ]);

        $response->assertOk();
        $this->assertSame('ok', $response->json('results.test-key-1.status'));

        $sale = Sale::query()->where('idempotency_key', 'test-key-1')->firstOrFail();

        // Before the sanctum guard fix, HasUserstamps::currentActorId()
        // only checked platform/web and would have left this null.
        $this->assertSame($owner->id, $sale->created_by);
        $this->assertSame($business->id, $sale->business_id);

        $auditEntry = AuditLog::query()
            ->where('auditable_type', Sale::class)
            ->where('auditable_id', $sale->id)
            ->where('action', 'created')
            ->firstOrFail();

        // Before the fix, Auditable::resolveActor() fell through to
        // [null, null] for a sanctum-authenticated request.
        $this->assertSame('user', $auditEntry->actor_type);
        $this->assertSame($owner->id, $auditEntry->actor_id);
    }
}
