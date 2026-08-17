<?php

namespace Tests\Feature\Backup;

use App\Domain\Backup\Services\TenantSqlExportService;
use App\Domain\Backup\Services\TenantSqlImportService;
use App\Domain\Backup\Support\SqlValue;
use App\Domain\Backup\Support\TenantTableMap;
use App\Domain\Inventory\Models\Category;
use App\Domain\Sales\Models\Customer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * The point of these tests is the security boundary, not the happy path.
 *
 * A vendor uploading a `.sql` file is the most dangerous input this
 * application accepts: if it were executed, any vendor could read or
 * rewrite every other business on the platform. So the cases that matter
 * most are the ones proving the file is parsed rather than run, that only
 * allow-listed tables are touched, and that `business_id` is always
 * rewritten to the importer regardless of what the file claims.
 */
class BusinessBackupTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The table map memoises the schema. RefreshDatabase rebuilds it
        // between cases, so a list resolved in one test must not carry
        // into the next.
        TenantTableMap::flush();

        $this->seed(PermissionSeeder::class);
    }

    public function test_the_export_contains_only_the_requesting_business(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$otherOwner, $otherBusiness] = $this->createOwnerWithBusiness();

        $mine = Customer::query()->create([
            'business_id' => $business->getKey(),
            'name' => 'My Customer',
        ]);

        $theirs = Customer::query()->create([
            'business_id' => $otherBusiness->getKey(),
            'name' => 'Their Customer',
        ]);

        $sql = $this->exportSql($business);

        $this->assertStringContainsString('My Customer', $sql);
        $this->assertStringNotContainsString('Their Customer', $sql);
        $this->assertStringNotContainsString($theirs->getKey(), $sql);
        $this->assertStringContainsString($mine->getKey(), $sql);
    }

    public function test_the_export_omits_billing_accounts_and_audit_tables(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $sql = $this->exportSql($business);

        foreach (array_keys(TenantTableMap::EXCLUDED) as $table) {
            $this->assertStringNotContainsString(
                "INSERT INTO `{$table}`",
                $sql,
                "Excluded table `{$table}` leaked into a tenant backup.",
            );
        }

        // The owner's own account exists, so this would appear if `users`
        // were included — which is the case the assertion above is really
        // guarding.
        $this->assertStringNotContainsString($owner->email, $sql);
    }

    public function test_a_backup_round_trips(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $category = Category::query()->create([
            'business_id' => $business->getKey(),
            'name' => 'Beverages',
            'slug' => 'beverages',
        ]);

        Customer::query()->create([
            'business_id' => $business->getKey(),
            // Values chosen to break a naive parser: a quote, a comma, a
            // backslash and a newline all inside one field.
            'name' => "O'Brien, Ltd \\ \"Trading\"\nSecond line",
        ]);

        $sql = $this->exportSql($business);
        $path = $this->writeTemp($sql);

        Category::query()->where('business_id', $business->getKey())->delete();
        Customer::query()->where('business_id', $business->getKey())->delete();

        $this->assertSame(0, Customer::query()->where('business_id', $business->getKey())->count());

        app(TenantSqlImportService::class)->restore($business, $path);

        $restored = Customer::query()->where('business_id', $business->getKey())->first();

        $this->assertNotNull($restored);
        $this->assertSame("O'Brien, Ltd \\ \"Trading\"\nSecond line", $restored->name);
        $this->assertSame(
            $category->name,
            Category::query()->where('business_id', $business->getKey())->value('name'),
        );
    }

    public function test_a_restore_replaces_rather_than_merges(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        Customer::query()->create(['business_id' => $business->getKey(), 'name' => 'In the backup']);

        $path = $this->writeTemp($this->exportSql($business));

        Customer::query()->create(['business_id' => $business->getKey(), 'name' => 'Added afterwards']);

        app(TenantSqlImportService::class)->restore($business, $path);

        $names = Customer::query()->where('business_id', $business->getKey())->pluck('name')->all();

        $this->assertSame(['In the backup'], $names);
    }

    /**
     * A backup belongs to the business it came from.
     *
     * Two reasons, and the first one is why an earlier version of this test
     * asserted the opposite and was wrong: primary keys are preserved so
     * that foreign keys inside the backup stay valid, so loading business
     * A's file into business B collides with A's rows, which still exist.
     * The second reason is the better one — a backup handed over by a
     * former employee or a competitor must not be loadable as "my data".
     */
    public function test_a_file_from_another_business_is_refused(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$otherOwner, $otherBusiness] = $this->createOwnerWithBusiness();

        Customer::query()->create(['business_id' => $otherBusiness->getKey(), 'name' => 'Borrowed']);
        Customer::query()->create(['business_id' => $business->getKey(), 'name' => 'Mine']);

        $path = $this->writeTemp($this->exportSql($otherBusiness));

        try {
            app(TenantSqlImportService::class)->restore($business, $path);
            $this->fail('Expected a restore from another business to be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('different business', $e->getMessage());
        }

        // Refused before anything was deleted — both businesses untouched.
        $this->assertSame(
            1,
            Customer::query()->where('business_id', $business->getKey())->where('name', 'Mine')->count(),
        );
        $this->assertSame(
            1,
            Customer::query()->where('business_id', $otherBusiness->getKey())->count(),
        );
    }

    /**
     * Regression guard for a bug that made every restore fail.
     *
     * `Schema::getTableListing()` without a schema argument lists tables
     * from every database the MySQL user can see. With a dev and a testing
     * database on one server — the normal XAMPP setup — each table name
     * came back twice, so the exporter dumped every row twice and the
     * importer hit a duplicate primary key.
     */
    public function test_the_table_map_contains_no_duplicates(): void
    {
        $direct = TenantTableMap::directTables();
        $all = TenantTableMap::allTables();

        $this->assertSame(array_values(array_unique($direct)), $direct);
        $this->assertSame(array_values(array_unique($all)), $all);
    }

    public function test_the_export_writes_each_row_exactly_once(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $customer = Customer::query()->create([
            'business_id' => $business->getKey(),
            'name' => 'Only Once',
        ]);

        $sql = $this->exportSql($business);

        $this->assertSame(1, substr_count($sql, $customer->getKey()));

        // Chart-of-accounts rows are seeded on registration, so this covers
        // a table with many rows rather than the single one above.
        $this->assertSame(
            \App\Domain\Finance\Models\Account::query()->where('business_id', $business->getKey())->count(),
            substr_count($sql, 'INSERT INTO `accounts`'),
        );
    }

    public function test_dangerous_statements_in_an_uploaded_file_are_ignored(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $tableCount = count(TenantTableMap::allTables());

        $sql = implode("\n", [
            TenantSqlExportService::FORMAT_HEADER,
            '-- format_version: 1',
            '',
            'DROP TABLE customers;',
            'DELETE FROM users;',
            "UPDATE subscriptions SET status = 'active';",
            "GRANT ALL PRIVILEGES ON *.* TO 'attacker'@'%';",
            "INSERT INTO `customers` (`id`, `business_id`, `name`) VALUES ('".\Illuminate\Support\Str::uuid()."', 'ignored-business', 'Legitimate');",
            "INSERT INTO `subscriptions` (`id`, `business_id`, `status`) VALUES ('".\Illuminate\Support\Str::uuid()."', '".$business->getKey()."', 'active');",
        ]);

        $result = app(TenantSqlImportService::class)->restore($business, $this->writeTemp($sql));

        // Everything that isn't one of our own INSERTs never reaches the
        // database — the tables are all still standing.
        $this->assertSame($tableCount, count(TenantTableMap::allTables()));
        $this->assertDatabaseHas('users', ['id' => $owner->getKey()]);

        // The allow-listed INSERT was applied, rewritten to this business.
        $this->assertSame(
            1,
            Customer::query()->where('business_id', $business->getKey())->where('name', 'Legitimate')->count(),
        );

        // The subscriptions INSERT was refused, not applied.
        $this->assertArrayHasKey('subscriptions', $result['skipped']);
        $this->assertArrayNotHasKey('subscriptions', $result['restored']);
    }

    public function test_a_file_that_is_not_a_biasharamax_backup_is_rejected(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $path = $this->writeTemp("-- MySQL dump 10.13\nINSERT INTO `customers` (`id`) VALUES ('x');");

        $this->expectExceptionMessageMatches('/not a BiasharaMax backup/');

        app(TenantSqlImportService::class)->restore($business, $path);
    }

    public function test_the_export_route_requires_permission(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->get(route('settings.backups.export'))->assertOk();

        $employee = $this->employeeWithoutBackupPermissions($business);

        $this->actingAs($employee)->get(route('settings.backups.export'))->assertForbidden();
        $this->actingAs($employee)->get(route('settings.backups.index'))->assertForbidden();
    }

    public function test_a_restore_requires_the_business_name_as_confirmation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        Customer::query()->create(['business_id' => $business->getKey(), 'name' => 'Still here']);

        $this->actingAs($owner)
            ->post(route('settings.backups.preview'), [
                'backup' => UploadedFile::fake()->createWithContent(
                    'backup.sql',
                    $this->exportSql($business),
                ),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->post(route('settings.backups.restore'), ['confirmation' => 'not the name'])
            ->assertSessionHasErrors('confirmation');

        // Nothing was touched by the failed attempt.
        $this->assertDatabaseHas('customers', ['name' => 'Still here']);
    }

    public function test_sql_values_round_trip_through_the_quoter(): void
    {
        $cases = [
            "plain",
            "it's quoted",
            'has "double" quotes',
            "line\nbreak",
            "tab\there",
            'back\\slash',
            '',
            '0012',
        ];

        foreach ($cases as $value) {
            $parsed = SqlValue::parseRow(SqlValue::quote($value));

            $this->assertSame([$value], $parsed, 'Failed to round-trip: '.json_encode($value));
        }

        $this->assertSame([null], SqlValue::parseRow(SqlValue::quote(null)));
        $this->assertSame([42], SqlValue::parseRow(SqlValue::quote(42)));
    }

    // ---------------------------------------------------------------

    private function exportSql(\App\Domain\Business\Models\Business $business): string
    {
        $sql = '';

        foreach (app(TenantSqlExportService::class)->stream($business) as $chunk) {
            $sql .= $chunk;
        }

        return $sql;
    }

    private function writeTemp(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bos-backup-');
        file_put_contents($path, $contents);

        return $path;
    }

    private function employeeWithoutBackupPermissions(\App\Domain\Business\Models\Business $business): \App\Domain\Authentication\Models\User
    {
        $role = \App\Domain\RBAC\Models\Role::query()
            ->where('business_id', $business->getKey())
            ->where('slug', \App\Domain\RBAC\Models\Role::CASHIER)
            ->firstOrFail();

        $employee = \App\Domain\Authentication\Models\User::query()->create([
            'business_id' => $business->getKey(),
            'role_id' => $role->getKey(),
            'name' => 'Cashier',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('Password123!'),
            'status' => \App\Domain\Authentication\Models\User::STATUS_ACTIVE,
        ]);

        $employee->roles()->sync([$role->getKey()]);
        $employee->forceFill(['email_verified_at' => now()])->save();

        return $employee->fresh();
    }
}
