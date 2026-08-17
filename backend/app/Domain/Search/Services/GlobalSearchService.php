<?php

namespace App\Domain\Search\Services;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\Income;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Inventory\Models\Brand;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Product;
use App\Domain\ModuleManagement\Services\BusinessModuleResolver;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Website\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The ⌘K search across the whole vendor dashboard.
 *
 * Two rules shape this service.
 *
 * **Every source is permission-gated.** Search is the easiest way to build
 * an accidental data leak: a cashier who cannot open the Suppliers screen
 * must not be able to read supplier names by typing them into a search
 * box. Each source below names the permission its own module enforces, and
 * a source whose permission the user lacks is never queried at all — not
 * queried and filtered, which would still show up in timing.
 *
 * **Tenancy is handled by the model, except where it isn't.** Nearly every
 * model here uses BelongsToTenant, whose global scope already restricts
 * queries to the signed-in user's business; repeating that in each source
 * would suggest the scope isn't trusted elsewhere. `users` is the
 * exception — it carries a `business_id` but not the trait — so the Staff
 * source declares an explicit scope. Any future source must check which
 * kind its model is.
 */
class GlobalSearchService
{
    public function __construct(
        private readonly BusinessModuleResolver $modules,
    ) {}

    /** Results per source. Deliberately small — this is a jump-to, not a report. */
    private const PER_SOURCE = 5;

    /** Below this, almost everything matches and the results are noise. */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Searches every source the user is allowed to see.
     *
     * @return list<array{group: string, items: list<array<string, mixed>>}>
     */
    public function search(User $user, string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_QUERY_LENGTH || $user->business === null) {
            return [];
        }

        $groups = [];

        // A section the Super Admin switched off is gone, not merely
        // unlisted — its routes 404. Returning results that link into it
        // would be worse than returning none.
        $hidden = $this->modules->hiddenSlugs($user->business);

        foreach ($this->sources() as $source) {
            if (! $this->permitted($user, $source['permissions'])) {
                continue;
            }

            if (in_array($source['module'], $hidden, true)) {
                continue;
            }

            $items = $this->run($user, $source, $term);

            if ($items->isNotEmpty()) {
                $groups[] = ['group' => $source['group'], 'items' => $items->all()];
            }
        }

        return $groups;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function permitted(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return Collection<int, array<string, mixed>>
     */
    private function run(User $user, array $source, string $term): Collection
    {
        /** @var Builder $query */
        $query = $source['model']::query();

        // Most models here carry BelongsToTenant and are already scoped by
        // its global scope. `users` is the exception — it has a business_id
        // column but no trait — so a source backed by it MUST supply its
        // own scope or search would return staff from every business on the
        // platform. Sources declare this rather than the loop guessing.
        if (isset($source['scope'])) {
            ($source['scope'])($query, $user);
        }

        // Grouped so the OR-ing of searchable columns can't escape any
        // other constraint the source added — a classic way for a search
        // filter to quietly widen a query.
        $query->where(function (Builder $inner) use ($source, $term): void {
            foreach ($source['columns'] as $column) {
                $inner->orWhere($column, 'like', '%'.$term.'%');
            }
        });

        if (isset($source['with'])) {
            $query->with($source['with']);
        }

        return $query
            ->limit(self::PER_SOURCE)
            ->get()
            ->map(fn (Model $record): array => [
                'id' => $source['group'].':'.$record->getKey(),
                'type' => $source['group'],
                'title' => ($source['title'])($record),
                'subtitle' => ($source['subtitle'])($record),
                'url' => ($source['url'])($record),
            ])
            ->values();
    }

    /**
     * The searchable surface of the dashboard.
     *
     * Ordered by how often people look for each thing, because the palette
     * renders groups in this order and the first one is what you see
     * without scrolling.
     *
     * @return list<array<string, mixed>>
     */
    private function sources(): array
    {
        return [
            [
                'group' => 'Products',
                'module' => 'inventory',
                'model' => Product::class,
                'permissions' => ['products.view'],
                'columns' => ['name', 'sku', 'barcode', 'custom_code'],
                'title' => fn (Product $p): string => $p->name,
                'subtitle' => fn (Product $p): ?string => $p->sku,
                'url' => fn (Product $p): string => route('inventory.products.show', $p->getKey()),
            ],
            [
                'group' => 'Customers',
                'module' => 'sales',
                'model' => Customer::class,
                'permissions' => ['customers.view', 'crm.view'],
                'columns' => ['name', 'phone', 'email'],
                'title' => fn (Customer $c): string => $c->name,
                'subtitle' => fn (Customer $c): ?string => $c->phone ?: $c->email,
                'url' => fn (Customer $c): string => route('crm.customers.show', $c->getKey()),
            ],
            [
                'group' => 'Sales',
                'module' => 'sales',
                'model' => Sale::class,
                'permissions' => ['sales.view'],
                'columns' => ['sale_number'],
                'with' => ['customer:id,name'],
                'title' => fn (Sale $s): string => $s->sale_number,
                'subtitle' => fn (Sale $s): ?string => $s->customer?->name ?? ucfirst((string) $s->status),
                'url' => fn (Sale $s): string => route('sales.orders.show', $s->getKey()),
            ],
            [
                'group' => 'Suppliers',
                'module' => 'inventory',
                'model' => Supplier::class,
                'permissions' => ['suppliers.view'],
                'columns' => ['name', 'phone', 'email'],
                'title' => fn (Supplier $s): string => $s->name,
                'subtitle' => fn (Supplier $s): ?string => $s->phone ?: $s->email,
                'url' => fn (Supplier $s): string => route('inventory.suppliers.index', ['q' => $s->name]),
            ],
            [
                'group' => 'Purchase Orders',
                'module' => 'purchasing',
                'model' => PurchaseOrder::class,
                'permissions' => ['purchase_orders.view'],
                'columns' => ['po_number'],
                'with' => ['supplier:id,name'],
                'title' => fn (PurchaseOrder $o): string => $o->po_number,
                'subtitle' => fn (PurchaseOrder $o): ?string => $o->supplier?->name ?? ucfirst((string) $o->status),
                'url' => fn (PurchaseOrder $o): string => route('purchasing.orders.show', $o->getKey()),
            ],
            [
                'group' => 'Returns',
                'module' => 'sales',
                'model' => SaleReturn::class,
                'permissions' => ['sales_returns.view'],
                'columns' => ['return_number'],
                'title' => fn (SaleReturn $r): string => $r->return_number,
                'subtitle' => fn (SaleReturn $r): ?string => ucfirst((string) $r->status),
                'url' => fn (SaleReturn $r): string => route('sales.returns.show', $r->getKey()),
            ],
            [
                'group' => 'Expenses',
                'module' => 'finance',
                'model' => Expense::class,
                'permissions' => ['accounting.view'],
                'columns' => ['title', 'description'],
                'title' => fn (Expense $e): string => $e->title,
                'subtitle' => fn (Expense $e): ?string => $e->expense_date?->format('d M Y'),
                'url' => fn (Expense $e): string => route('accounting.expenses.index', ['q' => $e->title]),
            ],
            [
                'group' => 'Income',
                'module' => 'finance',
                'model' => Income::class,
                'permissions' => ['accounting.view'],
                'columns' => ['title', 'description'],
                'title' => fn (Income $i): string => $i->title,
                'subtitle' => fn (Income $i): ?string => $i->income_date?->format('d M Y'),
                'url' => fn (Income $i): string => route('accounting.income.index', ['q' => $i->title]),
            ],
            [
                'group' => 'Journal Entries',
                'module' => 'finance',
                'model' => JournalEntry::class,
                'permissions' => ['finance.view'],
                'columns' => ['entry_number', 'description', 'memo'],
                'title' => fn (JournalEntry $j): string => $j->entry_number,
                'subtitle' => fn (JournalEntry $j): ?string => $j->description,
                'url' => fn (JournalEntry $j): string => route('finance.journal.show', $j->getKey()),
            ],
            [
                'group' => 'Accounts',
                'module' => 'finance',
                'model' => Account::class,
                'permissions' => ['finance.view'],
                'columns' => ['code', 'name'],
                'title' => fn (Account $a): string => $a->code.' — '.$a->name,
                'subtitle' => fn (Account $a): ?string => ucfirst((string) $a->type),
                'url' => fn (Account $a): string => route('finance.ledger.show', $a->getKey()),
            ],
            [
                'group' => 'Categories',
                'module' => 'inventory',
                'model' => Category::class,
                'permissions' => ['categories.view'],
                'columns' => ['name'],
                'title' => fn (Category $c): string => $c->name,
                'subtitle' => fn (Category $c): ?string => 'Product category',
                'url' => fn (Category $c): string => route('inventory.categories.index'),
            ],
            [
                'group' => 'Brands',
                'module' => 'inventory',
                'model' => Brand::class,
                'permissions' => ['brands.view'],
                'columns' => ['name'],
                'title' => fn (Brand $b): string => $b->name,
                'subtitle' => fn (Brand $b): ?string => 'Brand',
                'url' => fn (Brand $b): string => route('inventory.brands.index'),
            ],
            [
                'group' => 'Staff',
                'module' => 'employees',
                'model' => User::class,
                'permissions' => ['employees.view'],
                'columns' => ['name', 'email', 'phone', 'username'],
                // The one source that needs this: User has no
                // BelongsToTenant, so without it a search for "john" would
                // list every John on the platform.
                'scope' => fn (Builder $q, User $u) => $q->where('business_id', $u->business_id),
                'title' => fn (User $u): string => $u->name,
                'subtitle' => fn (User $u): ?string => $u->email,
                'url' => fn (User $u): string => route('settings.employees.index'),
            ],
            [
                'group' => 'Branches',
                'module' => 'business',
                'model' => Branch::class,
                'permissions' => ['branches.view'],
                'columns' => ['name', 'code', 'city'],
                'title' => fn (Branch $b): string => $b->name,
                'subtitle' => fn (Branch $b): ?string => $b->city,
                'url' => fn (Branch $b): string => route('settings.branches.index'),
            ],
            [
                'group' => 'Warehouses',
                'module' => 'business',
                'model' => Warehouse::class,
                'permissions' => ['warehouses.view'],
                'columns' => ['name', 'code'],
                'title' => fn (Warehouse $w): string => $w->name,
                'subtitle' => fn (Warehouse $w): ?string => $w->code,
                'url' => fn (Warehouse $w): string => route('settings.warehouses.index'),
            ],
            [
                'group' => 'Blog',
                'module' => 'website',
                'model' => Article::class,
                'permissions' => ['website.view'],
                'columns' => ['title', 'excerpt'],
                'title' => fn (Article $a): string => $a->title,
                'subtitle' => fn (Article $a): ?string => ucfirst((string) $a->status),
                'url' => fn (Article $a): string => route('website.blog.show', $a->getKey()),
            ],
        ];
    }
}
