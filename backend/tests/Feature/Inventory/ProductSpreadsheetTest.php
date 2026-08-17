<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Models\InventoryImportLog;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\InventoryExportService;
use App\Domain\Inventory\Services\InventoryImportService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Product import and export as spreadsheets.
 *
 * The property that matters most is the **round trip**: a vendor exports
 * their catalogue, edits it in Excel, and imports it back. That only
 * works if the export's header row is exactly what the importer expects,
 * and those live in two different classes — so it is asserted rather
 * than assumed.
 *
 * The rest of these cover the ways spreadsheets differ from CSV and
 * quietly corrupt data: Excel turning `0012` into `12`, trailing blank
 * rows that look deleted, and vendors renaming the headers.
 */
class ProductSpreadsheetTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_the_export_downloads_as_a_spreadsheet(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->product($business->id, 'Sugar 1kg', 'SKU-001');

        $response = $this->actingAs($owner)->get(route('inventory.export.show'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        // A real workbook, not a CSV with an .xlsx name — the ZIP magic
        // bytes every OOXML file starts with.
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    /**
     * The whole point of the feature: export, edit, import back.
     *
     * The export's headers and the importer's expectations live in
     * separate classes, so nothing but a test stops them drifting apart
     * — and when they do, every row fails with "name is required", which
     * points at the wrong problem entirely.
     */
    public function test_an_exported_file_can_be_imported_back(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->product($business->id, 'Sugar 1kg', 'SKU-001');
        $this->product($business->id, 'Rice 5kg', 'SKU-002');

        $binary = app(InventoryExportService::class)->exportXlsx($business->id);

        $log = $this->importBinary($business->id, $owner->id, $binary);

        $this->assertSame(0, $log->failure_count, 'A file this app exported must import cleanly.');
        $this->assertSame(2, $log->success_count);

        // Two, not four. Import matches on SKU and updates — importing
        // always created, so re-importing an export hit the unique index
        // on (business_id, sku) and the round trip failed on every row.
        $this->assertSame(2, Product::query()->where('business_id', $business->id)->count());
    }

    /**
     * The edit half of the round trip.
     *
     * Exporting and importing an unchanged file proves nothing on its
     * own — the point is that a vendor changes a price in Excel and the
     * change lands.
     */
    public function test_edits_made_in_the_spreadsheet_are_applied(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->product($business->id, 'Sugar 1kg', 'SKU-001');

        $csv = "name,sku,selling_price,wholesale_price,status\n"
            ."Sugar 1kg,SKU-001,9999,8888,inactive\n";

        $log = $this->importBinary($business->id, $owner->id, $csv, 'csv');

        $this->assertSame(0, $log->failure_count);

        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();

        $this->assertSame('9999.00', (string) $product->selling_price);
        // Both of these were exported and then silently dropped on the
        // way back in, so editing them did nothing at all.
        $this->assertSame('8888.00', (string) $product->wholesale_price);
        $this->assertSame(Product::STATUS_INACTIVE, $product->status);
    }

    public function test_an_invalid_status_is_reported_rather_than_defaulted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $csv = "name,sku,status\nMystery Item,SKU-X,activ\n";

        $log = $this->importBinary($business->id, $owner->id, $csv, 'csv');

        // Quietly defaulting to active would publish a product the
        // vendor meant to keep hidden.
        $this->assertSame(1, $log->failure_count);
        $this->assertSame(0, $log->success_count);
    }

    /**
     * Excel eats leading zeros on anything that looks numeric.
     *
     * A SKU of `0012` becoming `12` is silent data loss that only
     * surfaces when the file is imported back and stops matching.
     */
    public function test_leading_zeros_survive_the_export(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->product($business->id, 'Zero SKU', '0012', barcode: '0009781234567');

        $rows = $this->readBinary(
            app(InventoryExportService::class)->exportXlsx($business->id),
        );

        $this->assertSame('0012', $rows[1][1]);
        $this->assertSame('0009781234567', $rows[1][2]);
    }

    public function test_the_template_carries_the_same_headers_as_the_export(): void
    {
        $template = $this->readBinary(app(InventoryExportService::class)->templateXlsx());

        $this->assertSame(InventoryExportService::COLUMNS, $template[0]);
    }

    public function test_the_template_is_downloadable_without_export_permission(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        // Holds no business data — only column headings — so needing the
        // export permission to see it would block whoever prepares the
        // file from whoever is allowed to run it.
        $this->actingAs($owner)
            ->get(route('inventory.import.template'))
            ->assertOk();
    }

    public function test_csv_is_still_accepted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $csv = "name,sku,selling_price\nMaize Flour,SKU-CSV,2500\n";

        $log = $this->importBinary($business->id, $owner->id, $csv, 'csv');

        $this->assertSame(1, $log->success_count);
        $this->assertSame(0, $log->failure_count);
        $this->assertDatabaseHas('products', [
            'business_id' => $business->id,
            'sku' => 'SKU-CSV',
        ]);
    }

    /**
     * Vendors rename headers — "Selling Price" is the obvious thing to
     * type. Before aliasing, that failed every row with "name is
     * required", which blames the wrong column entirely.
     */
    public function test_friendly_header_names_are_understood(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $csv = "Product Name,Item Code,Selling Price\nCooking Oil,SKU-ALIAS,7800\n";

        $log = $this->importBinary($business->id, $owner->id, $csv, 'csv');

        $this->assertSame(1, $log->success_count);
        $this->assertDatabaseHas('products', [
            'business_id' => $business->id,
            'name' => 'Cooking Oil',
            'sku' => 'SKU-ALIAS',
        ]);
    }

    /**
     * Deleting content in Excel leaves the rows behind. Importing them
     * reports failures for rows the vendor believes they removed.
     */
    public function test_trailing_blank_rows_are_ignored(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $csv = "name,sku\nSalt 500g,SKU-S1\n,\n,\n,\n";

        $log = $this->importBinary($business->id, $owner->id, $csv, 'csv');

        $this->assertSame(1, $log->total_rows);
        $this->assertSame(0, $log->failure_count);
    }

    public function test_a_bad_row_is_reported_without_stopping_the_rest(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        // Second row has no name, which is the one required field.
        $csv = "name,sku\nGood Product,SKU-OK\n,SKU-BAD\nAnother Good,SKU-OK2\n";

        $log = $this->importBinary($business->id, $owner->id, $csv, 'csv');

        $this->assertSame(2, $log->success_count);
        $this->assertSame(1, $log->failure_count);

        // The report is a spreadsheet too — it is the file the vendor
        // opens to fix their data.
        $this->assertStringEndsWith('.xlsx', (string) $log->error_report_path);
        $this->assertTrue(Storage::exists($log->error_report_path));
    }

    /**
     * The wrong file entirely.
     *
     * PhpSpreadsheet does not reject non-spreadsheets — its CSV reader
     * parses arbitrary text as a one-column sheet — so this used to
     * "complete" with zero rows and no explanation. The header check is
     * what turns that into something a vendor can act on.
     */
    public function test_a_file_that_is_not_a_product_list_is_rejected_by_name(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $log = $this->makeLog($business->id, $owner->id, 'not a spreadsheet at all', 'xlsx');

        $this->expectExceptionMessageMatches('/no "name" column/');

        app(InventoryImportService::class)->import($log);
    }

    // ---------------------------------------------------------------

    private function product(string $businessId, string $name, string $sku, ?string $barcode = null): Product
    {
        return Product::query()->create([
            'business_id' => $businessId,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.\Illuminate\Support\Str::random(5),
            'sku' => $sku,
            'barcode' => $barcode,
            'product_type' => 'simple',
            'status' => Product::STATUS_ACTIVE,
            'cost_price' => 1000,
            'selling_price' => 1500,
        ]);
    }

    private function makeLog(string $businessId, string $userId, string $contents, string $extension): InventoryImportLog
    {
        $path = 'imports/'.\Illuminate\Support\Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $contents);

        return InventoryImportLog::create([
            'business_id' => $businessId,
            'file_path' => $path,
            'status' => InventoryImportLog::STATUS_PROCESSING,
            'created_by' => $userId,
        ]);
    }

    private function importBinary(string $businessId, string $userId, string $contents, string $extension = 'xlsx'): InventoryImportLog
    {
        return app(InventoryImportService::class)
            ->import($this->makeLog($businessId, $userId, $contents, $extension));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readBinary(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet').'.xlsx';
        file_put_contents($path, $binary);

        $rows = IOFactory::createReaderForFile($path)
            ->load($path)
            ->getActiveSheet()
            ->toArray(null, true, false, false);

        unlink($path);

        return $rows;
    }
}
