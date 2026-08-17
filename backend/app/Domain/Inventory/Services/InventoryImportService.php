<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Events\BulkImportCompleted;
use App\Domain\Inventory\Models\Brand;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\InventoryImportLog;
use App\Domain\Inventory\Models\Unit;
use App\Domain\Shared\Support\SpreadsheetWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Bulk product import from .xlsx, .xls or .csv. Each row is processed
 * independently — a bad row
 * never aborts the whole file, it's just recorded in the error report so
 * the business can fix and re-import only what failed.
 *
 * Expected columns: name, sku, category, brand, unit, cost_price,
 * selling_price, reorder_level, barcode (optional).
 */
class InventoryImportService
{
    /**
     * Header spellings accepted in addition to the canonical field name.
     *
     * The importer's contract is the header row, and vendors edit it —
     * they type what the column means rather than what the database
     * calls it. Every entry here is a spelling seen in a real
     * spreadsheet or produced by a competing product's export.
     *
     * @var array<string, string>
     */
    private const COLUMN_ALIASES = [
        'product_name' => 'name',
        'product' => 'name',
        'item' => 'name',
        'item_name' => 'name',
        'code' => 'sku',
        'product_code' => 'sku',
        'item_code' => 'sku',
        'ean' => 'barcode',
        'upc' => 'barcode',
        'buying_price' => 'cost_price',
        'purchase_price' => 'cost_price',
        'buy_price' => 'cost_price',
        'cost' => 'cost_price',
        'price' => 'selling_price',
        'sale_price' => 'selling_price',
        'retail_price' => 'selling_price',
        'sell_price' => 'selling_price',
        'bulk_price' => 'wholesale_price',
        'reorder' => 'reorder_level',
        'reorder_point' => 'reorder_level',
        'min_stock' => 'reorder_level',
        'minimum_stock' => 'reorder_level',
        'category_name' => 'category',
        'brand_name' => 'brand',
        'unit_name' => 'unit',
        'uom' => 'unit',
    ];

    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function import(InventoryImportLog $log): InventoryImportLog
    {
        $rows = $this->readRows(Storage::path($log->file_path));
        $errors = [];
        $successCount = 0;

        foreach ($rows as $index => $row) {
            try {
                $this->importRow($log->business_id, $row);
                $successCount++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $index + 2, // +1 for header, +1 for 1-based line numbers
                    'data' => implode(',', $row),
                    'error' => $e->getMessage(),
                ];
            }
        }

        $errorReportPath = $errors !== [] ? $this->writeErrorReport($log, $errors) : null;

        $log->update([
            'status' => InventoryImportLog::STATUS_COMPLETED,
            'total_rows' => count($rows),
            'success_count' => $successCount,
            'failure_count' => count($errors),
            'error_report_path' => $errorReportPath,
        ]);

        BulkImportCompleted::dispatch($log);

        return $log;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(string $businessId, array $row): void
    {
        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('Product name is required.');
        }

        $sku = trim((string) ($row['sku'] ?? '')) ?: null;

        $attributes = [
            'name' => $name,
            'sku' => $sku,
            'barcode' => trim((string) ($row['barcode'] ?? '')) ?: null,
            'category_id' => $this->resolveLookupId(Category::class, $businessId, $row['category'] ?? null),
            'brand_id' => $this->resolveLookupId(Brand::class, $businessId, $row['brand'] ?? null),
            'unit_id' => $this->resolveUnitId($businessId, $row['unit'] ?? null),
            'cost_price' => $this->toDecimal($row['cost_price'] ?? null),
            'selling_price' => $this->toDecimal($row['selling_price'] ?? null),
            // Both exported and both previously dropped on the way back
            // in, so editing either one in the spreadsheet silently did
            // nothing — the sort of gap that only shows up when someone
            // trusts the feature with real work.
            'wholesale_price' => $this->toDecimal($row['wholesale_price'] ?? null) ?: null,
            'status' => $this->resolveStatus($row['status'] ?? null),
            'reorder_level' => $this->toDecimal($row['reorder_level'] ?? null),
        ];

        $existing = $this->findBySku($businessId, $sku);

        if (! $existing) {
            $this->productService->create($businessId, $attributes);

            return;
        }

        /*
         * A soft-deleted product still occupies its SKU.
         *
         * The unique index on (business_id, sku) is enforced by the
         * database, which knows nothing about `deleted_at` — so creating
         * a new row would fail with a constraint violation that reads
         * like a bug. Restoring is also what the vendor means: the
         * product is in the sheet they just uploaded.
         */
        if ($existing->trashed()) {
            $existing->restore();
        }

        $this->productService->update($existing, $attributes);
    }

    /**
     * Finds an existing product by SKU so an import updates rather than
     * duplicates.
     *
     * This is what makes "export, edit in Excel, import back" work —
     * the headline reason for the feature. Importing always created,
     * so a re-imported export hit the unique index on every row and the
     * round trip failed entirely.
     *
     * SKU is the business key here. Rows without one always create,
     * because there is nothing to match on and guessing by name would
     * silently merge two genuinely different products that happen to
     * share a label.
     */
    private function findBySku(string $businessId, ?string $sku): ?\App\Domain\Inventory\Models\Product
    {
        if ($sku === null) {
            return null;
        }

        return \App\Domain\Inventory\Models\Product::withTrashed()
            ->where('business_id', $businessId)
            ->where('sku', $sku)
            ->first();
    }

    private function resolveStatus(mixed $value): string
    {
        $status = strtolower(trim((string) ($value ?? '')));

        if ($status === '') {
            return \App\Domain\Inventory\Models\Product::STATUS_ACTIVE;
        }

        $allowed = [
            \App\Domain\Inventory\Models\Product::STATUS_ACTIVE,
            \App\Domain\Inventory\Models\Product::STATUS_INACTIVE,
        ];

        if (! in_array($status, $allowed, true)) {
            // Named rather than silently defaulted: a typo in this column
            // would otherwise quietly publish a product the vendor meant
            // to keep hidden.
            throw new \InvalidArgumentException(
                "Status must be either active or inactive, not \"{$value}\".",
            );
        }

        return $status;
    }

    /**
     * @return array<int, array<string, string>>
     */
    /**
     * Reads .xlsx, .xls or .csv into keyed rows.
     *
     * The format is detected from the file's own contents by
     * PhpSpreadsheet rather than trusted from the extension — a vendor
     * exporting from another system routinely produces a file named
     * `.xls` that is really CSV or HTML, and the upload validation only
     * sees the name.
     *
     * CSV is still accepted deliberately. A business migrating from
     * another till almost always has CSV to hand, and rejecting it would
     * turn a working path into a support ticket for no benefit.
     *
     * @return array<int, array<string, string|null>>
     */
    private function readRows(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Could not read that file. Save it as .xlsx or .csv and try again.',
                previous: $e,
            );
        }

        // Formatting, formulas and images are irrelevant here and are the
        // expensive part of loading a workbook — a 5,000 row sheet reads
        // several times faster and in a fraction of the memory this way.
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($path)->getActiveSheet();

        // `toArray` with calculateFormulas off returns raw values, so a
        // price entered as `=B2*1.2` arrives as its computed number
        // rather than the formula string.
        $matrix = $sheet->toArray(null, true, false, false);

        if ($matrix === []) {
            return [];
        }

        $header = $this->normaliseHeader(array_shift($matrix));

        /*
         * The header has to look like a product sheet.
         *
         * PhpSpreadsheet does not reject a file that is not a
         * spreadsheet — its CSV reader will happily parse arbitrary text
         * as a single-column sheet. Without this check, uploading the
         * wrong file entirely produced a "completed" import of zero rows
         * and no explanation at all.
         *
         * `name` is the one genuinely required column, so its absence is
         * the cheapest reliable signal that the first row is not a
         * header.
         */
        if (! in_array('name', $header, true)) {
            throw new \RuntimeException(
                'That file does not look like a product list — its first row has no "name" column. '
                .'Download the template and try again, or save your file as .xlsx or .csv.',
            );
        }

        $rows = [];

        foreach ($matrix as $line) {
            $line = array_pad(array_slice($line, 0, count($header)), count($header), null);
            $row = array_combine($header, $line);

            // Spreadsheets are full of trailing blank rows — a vendor
            // deleting content leaves the rows behind. Importing them
            // would report dozens of "name is required" failures for
            // rows the vendor believes they removed.
            if ($this->isBlankRow($row)) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Lower-cases, trims and maps friendly header names onto field names.
     *
     * Vendors rename headers — "Selling Price" instead of
     * `selling_price` is the obvious thing to type — and a mismatch
     * previously failed every row with "name is required", which points
     * at the wrong problem entirely.
     *
     * @param  array<int, mixed>  $header
     * @return array<int, string>
     */
    private function normaliseHeader(array $header): array
    {
        return array_map(function ($column): string {
            $key = strtolower(trim((string) $column));
            // "Selling Price", "selling-price" and "Selling  Price" all
            // become `selling_price`.
            $key = preg_replace('/[\s\-]+/', '_', $key) ?? $key;

            return self::COLUMN_ALIASES[$key] ?? $key;
        }, $header);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function resolveLookupId(string $modelClass, string $businessId, ?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return $modelClass::query()->firstOrCreate(
            ['business_id' => $businessId, 'slug' => Str::slug($name)],
            ['name' => $name],
        )->id;
    }

    private function resolveUnitId(string $businessId, ?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return Unit::query()->firstOrCreate(
            ['business_id' => $businessId, 'symbol' => Str::slug($name, '')],
            ['name' => $name],
        )->id;
    }

    private function toDecimal(?string $value): float
    {
        $value = trim((string) $value);

        return $value === '' ? 0.0 : (float) $value;
    }

    /**
     * @param  array<int, array{row: int, data: string, error: string}>  $errors
     */
    /**
     * The failed rows, as a spreadsheet the vendor can actually work from.
     *
     * This is the file someone opens when an import half-worked, so it
     * matters more than the export does. Headers are written in plain
     * words rather than field names — the audience is whoever filled in
     * the sheet, not a developer reading a log.
     *
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function writeErrorReport(InventoryImportLog $log, array $errors): string
    {
        $path = "imports/errors/{$log->id}.xlsx";

        $writer = (new SpreadsheetWriter('Rows not imported'))
            ->headers(['Row in your file', 'What was in the row', 'Why it was not imported']);

        foreach ($errors as $error) {
            $writer->row([
                (int) $error['row'],
                $error['data'],
                $error['error'],
            ]);
        }

        // Wider than the default cap: the reason is a sentence, and
        // truncating the one column that explains the failure would
        // defeat the purpose of the file.
        $writer->autoSizeColumns(max: 70);

        Storage::put($path, $writer->toBinary());

        return $path;
    }
}
