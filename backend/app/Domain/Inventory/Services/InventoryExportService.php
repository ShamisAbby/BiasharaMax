<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\Product;
use App\Domain\Shared\Support\SpreadsheetWriter;
use Illuminate\Support\LazyCollection;

/**
 * Product catalogue export.
 *
 * XLSX rather than CSV because the audience is shop owners, not
 * developers: a CSV opens in Excel with everything crammed into column
 * A on some locales, turns a SKU of `0012` into `12`, and gives no hint
 * which of two price columns is which once you have scrolled past the
 * header. The formatting in SpreadsheetWriter is the point of the
 * change, not decoration.
 *
 * The column list is shared with the import so a file exported here can
 * be edited and imported straight back — the single most common thing a
 * vendor wants to do with this feature, and the thing that breaks the
 * moment the two lists drift apart.
 */
class InventoryExportService
{
    /**
     * Exactly the headers the importer expects. See
     * InventoryImportService::COLUMN_ALIASES.
     */
    public const COLUMNS = [
        'name', 'sku', 'barcode', 'category', 'brand', 'unit',
        'cost_price', 'selling_price', 'wholesale_price', 'reorder_level', 'status',
    ];

    /** 1-based positions of the money columns, for number formatting. */
    private const MONEY_COLUMNS = [7, 8, 9];

    private const STATUS_COLUMN = 11;

    public function exportXlsx(string $businessId): string
    {
        $writer = (new SpreadsheetWriter('Products'))->headers(self::COLUMNS);

        // Still a cursor: the sheet is built row by row rather than
        // materialising the whole catalogue as models first.
        $this->productsCursor($businessId)->each(
            fn (Product $product) => $writer->row($this->toRow($product)),
        );

        $lastRow = $writer->lastRow();

        foreach (self::MONEY_COLUMNS as $position) {
            $writer->moneyColumn($position, $lastRow);
        }

        $writer
            ->dropdownColumn(self::STATUS_COLUMN, $this->statuses(), $lastRow)
            ->autoSizeColumns();

        return $writer->toBinary();
    }

    /**
     * A blank workbook with the right headers and one worked example.
     *
     * Vendors were otherwise guessing column names from documentation,
     * or exporting first just to see the shape. The example row is
     * deliberately obvious enough to delete — `Example product` rather
     * than something that could be mistaken for real stock.
     */
    public function templateXlsx(): string
    {
        $writer = (new SpreadsheetWriter('Products'))
            ->headers(self::COLUMNS)
            ->row([
                'Example product', 'SKU-001', '6001234567890',
                'Beverages', 'Coca-Cola', 'pcs',
                1200.00, 1500.00, 1350.00, 10, 'active',
            ]);

        // Validation and formatting extend well past the single example
        // so they still apply to rows the vendor adds underneath.
        $lastRow = 200;

        foreach (self::MONEY_COLUMNS as $position) {
            $writer->moneyColumn($position, $lastRow);
        }

        return $writer
            ->dropdownColumn(self::STATUS_COLUMN, $this->statuses(), $lastRow)
            ->autoSizeColumns()
            ->toBinary();
    }

    /**
     * @return array<int, mixed>
     */
    private function toRow(Product $product): array
    {
        return [
            $product->name,
            $product->sku,
            $product->barcode,
            $product->category?->name,
            $product->brand?->name,
            $product->unit?->symbol,
            // Cast so the writer emits real numbers; these are decimal
            // strings off the model and would otherwise be written as
            // text, which Excel then refuses to sum.
            (float) $product->cost_price,
            (float) $product->selling_price,
            $product->wholesale_price !== null ? (float) $product->wholesale_price : null,
            $product->reorder_level !== null ? (int) $product->reorder_level : null,
            $product->status,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return [Product::STATUS_ACTIVE, Product::STATUS_INACTIVE];
    }

    /**
     * @return LazyCollection<int, Product>
     */
    private function productsCursor(string $businessId): LazyCollection
    {
        return Product::query()
            ->where('business_id', $businessId)
            ->with(['category', 'brand', 'unit'])
            ->orderBy('name')
            ->cursor();
    }
}
