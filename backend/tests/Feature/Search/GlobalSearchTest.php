<?php

namespace Tests\Feature\Search;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\RBAC\Models\Role;
use App\Domain\Sales\Models\Customer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Search is the easiest place in an application to build an accidental
 * data leak, so that is what these tests are mostly about.
 *
 * A search box that queries every table and filters afterwards will happily
 * tell a cashier the names of every supplier, and a search over a model
 * without a tenant scope will tell them the names of every customer on the
 * platform. Both are one forgotten line away, and neither shows up as a
 * visible bug — the feature looks like it works.
 */
class GlobalSearchTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_it_searches_across_modules_not_just_products(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->makeProduct($business, 'Nairobi Coffee Beans');
        Customer::query()->create(['business_id' => $business->getKey(), 'name' => 'Nairobi Traders']);
        Supplier::query()->create(['business_id' => $business->getKey(), 'name' => 'Nairobi Wholesale']);

        $groups = $this->search($owner, 'Nairobi');

        $this->assertEqualsCanonicalizing(
            ['Products', 'Customers', 'Suppliers'],
            array_column($groups, 'group'),
        );
    }

    public function test_a_cashier_cannot_search_data_they_cannot_open(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->makeProduct($business, 'Zanzibar Spice');
        Supplier::query()->create(['business_id' => $business->getKey(), 'name' => 'Zanzibar Imports']);

        $cashier = $this->employeeWithRole($business, Role::CASHIER);

        // The cashier role can see products but not suppliers, so the
        // supplier must not appear — a search result is a disclosure just
        // as much as a list page is.
        $this->assertTrue($cashier->hasPermission('products.view'));
        $this->assertFalse($cashier->hasPermission('suppliers.view'));

        $groups = $this->search($cashier, 'Zanzibar');
        $names = array_column($groups, 'group');

        $this->assertContains('Products', $names);
        $this->assertNotContains('Suppliers', $names);
    }

    public function test_it_never_returns_another_businesses_records(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$otherOwner, $otherBusiness] = $this->createOwnerWithBusiness();

        Customer::query()->create(['business_id' => $business->getKey(), 'name' => 'Shared Name Ltd']);
        Customer::query()->create(['business_id' => $otherBusiness->getKey(), 'name' => 'Shared Name Ltd']);

        $groups = $this->search($owner, 'Shared Name');
        $customers = collect($groups)->firstWhere('group', 'Customers');

        $this->assertNotNull($customers);
        $this->assertCount(1, $customers['items']);
    }

    /**
     * `users` carries a `business_id` but not the BelongsToTenant trait, so
     * the Staff source has to scope itself. Without that, searching a
     * common first name would list staff from every business on the
     * platform — the one source here where the model gives no protection.
     */
    public function test_staff_search_does_not_cross_businesses(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$otherOwner, $otherBusiness] = $this->createOwnerWithBusiness();

        $mine = $this->employeeWithRole($business, Role::CASHIER, 'Amina Similar');
        $theirs = $this->employeeWithRole($otherBusiness, Role::CASHIER, 'Amina Similar');

        $groups = $this->search($owner, 'Amina Similar');
        $staff = collect($groups)->firstWhere('group', 'Staff');

        $this->assertNotNull($staff, 'Expected the owner to be able to search staff.');
        $this->assertCount(1, $staff['items']);
        $this->assertStringContainsString($mine->getKey(), $staff['items'][0]['id']);
        $this->assertStringNotContainsString($theirs->getKey(), $staff['items'][0]['id']);
    }

    public function test_a_single_character_returns_nothing(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->makeProduct($business, 'Aardvark');

        // One character matches almost everything, so the results would be
        // noise and the query would scan every searchable table.
        $this->assertSame([], $this->search($owner, 'A'));
    }

    public function test_results_carry_a_working_link(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $product = $this->makeProduct($business, 'Traceable Item');

        $groups = $this->search($owner, 'Traceable');
        $item = collect($groups)->firstWhere('group', 'Products')['items'][0];

        $this->assertSame(route('inventory.products.show', $product->getKey()), $item['url']);
        $this->assertSame('Traceable Item', $item['title']);
    }

    // ---------------------------------------------------------------

    /**
     * @return list<array{group: string, items: list<array<string, mixed>>}>
     */
    private function search(User $user, string $term): array
    {
        $response = $this->actingAs($user)->getJson(route('search', ['q' => $term]));

        $response->assertOk();

        return $response->json('groups');
    }

    private function makeProduct(Business $business, string $name): Product
    {
        return Product::query()->create([
            'business_id' => $business->getKey(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'sku' => strtoupper(\Illuminate\Support\Str::random(8)),
        ]);
    }

    private function employeeWithRole(Business $business, string $roleSlug, string $name = 'Staff Member'): User
    {
        $role = Role::query()
            ->where('business_id', $business->getKey())
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $employee = User::query()->create([
            'business_id' => $business->getKey(),
            'role_id' => $role->getKey(),
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('Password123!'),
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee->roles()->sync([$role->getKey()]);
        $employee->forceFill(['email_verified_at' => now()])->save();

        return $employee->fresh();
    }
}
