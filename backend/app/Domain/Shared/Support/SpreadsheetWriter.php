<?php

namespace App\Domain\Shared\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Produces the .xlsx files vendors actually open.
 *
 * The point of moving off CSV was readability for people who are not
 * technical, and a bare .xlsx opens looking much like the CSV did. The
 * formatting here is the feature: a bold frozen header so scrolling a
 * long catalogue never loses the column names, widths sized to the
 * content so nothing reads as `####`, prices right-aligned as numbers
 * rather than left-aligned text, and dropdowns where a column only
 * accepts certain words.
 *
 * Shared by the product export, the blank import template and the import
 * error report. Three places generating their own spreadsheets is how
 * the export ends up with different column names from the template that
 * is supposed to feed it.
 */
class SpreadsheetWriter
{
    private Spreadsheet $spreadsheet;

    private int $rowCursor = 1;

    /** @var array<int, string> */
    private array $headers = [];

    public function __construct(string $sheetTitle = 'Sheet1')
    {
        $this->spreadsheet = new Spreadsheet;
        // Excel refuses sheet names over 31 characters and silently
        // mangles several punctuation marks, so this is clamped rather
        // than trusted from a caller.
        $this->spreadsheet->getActiveSheet()->setTitle(
            mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $sheetTitle) ?: 'Sheet1', 0, 31),
        );
    }

    /**
     * @param  array<int, string>  $headers
     */
    public function headers(array $headers): self
    {
        $this->headers = $headers;
        $sheet = $this->spreadsheet->getActiveSheet();

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }

        $lastColumn = $this->columnLetter(count($headers));

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);

        // Freezing at A2 keeps row 1 visible while scrolling. Without it,
        // a vendor 200 rows into a catalogue has no idea which column is
        // cost price and which is selling price.
        $sheet->freezePane('A2');

        // Excel's own filter row, so sorting and filtering are available
        // without anyone knowing how to add them.
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        $this->rowCursor = 2;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public function row(array $values): self
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        foreach ($values as $index => $value) {
            /*
             * Numbers written as numbers, everything else as an explicit
             * string.
             *
             * Without this, Excel helpfully reinterprets anything that
             * looks numeric — a SKU of `0012` loses its leading zeros and
             * a barcode long enough becomes `9.78E+12`. Both are data
             * loss that only shows up when the file is imported back.
             */
            if (is_int($value) || is_float($value)) {
                $sheet->setCellValue([$index + 1, $this->rowCursor], $value);
            } else {
                $sheet->setCellValueExplicit(
                    [$index + 1, $this->rowCursor],
                    (string) ($value ?? ''),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
                );
            }
        }

        $this->rowCursor++;

        return $this;
    }

    /**
     * Formats a column as money, by 1-based column position.
     */
    public function moneyColumn(int $position, int $lastRow): self
    {
        $letter = $this->columnLetter($position);

        $this->spreadsheet->getActiveSheet()
            ->getStyle("{$letter}2:{$letter}{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        return $this;
    }

    /**
     * Restricts a column to a fixed list, shown as a dropdown.
     *
     * Applied down to `$lastRow` rather than the whole column: Excel
     * validates lazily and a validation covering a million rows makes
     * the file noticeably slower to open.
     *
     * @param  array<int, string>  $options
     */
    public function dropdownColumn(int $position, array $options, int $lastRow): self
    {
        $letter = $this->columnLetter($position);
        $sheet = $this->spreadsheet->getActiveSheet();

        for ($row = 2; $row <= $lastRow; $row++) {
            $validation = $sheet->getCell("{$letter}{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Not a valid value');
            $validation->setError('Choose one of: '.implode(', ', $options));
            // Excel requires the list quoted and comma-separated.
            $validation->setFormula1('"'.implode(',', $options).'"');
        }

        return $this;
    }

    /**
     * Sizes every column to its content, within sensible bounds.
     *
     * Auto-size alone produces a 90-character column for one long
     * description, pushing everything else off screen — so the result is
     * clamped. Called last, because it measures what has been written.
     */
    public function autoSizeColumns(int $min = 12, int $max = 40): self
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        foreach (range(1, max(1, count($this->headers))) as $position) {
            $letter = $this->columnLetter($position);
            $dimension = $sheet->getColumnDimension($letter);

            $dimension->setAutoSize(true);
        }

        // Auto-size is computed by the writer, so the clamp has to be
        // applied as an explicit width afterwards.
        $sheet->calculateColumnWidths();

        foreach (range(1, max(1, count($this->headers))) as $position) {
            $letter = $this->columnLetter($position);
            $dimension = $sheet->getColumnDimension($letter);
            $width = $dimension->getWidth();

            $dimension->setAutoSize(false);
            $dimension->setWidth(max($min, min($max, $width > 0 ? $width + 2 : $min)));
        }

        return $this;
    }

    public function lastRow(): int
    {
        return max(1, $this->rowCursor - 1);
    }

    /**
     * The file as a binary string, ready to hand to a download response.
     */
    public function toBinary(): string
    {
        $writer = new Xlsx($this->spreadsheet);

        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();

        // PhpSpreadsheet holds the whole workbook in memory; releasing it
        // matters when this runs inside a queued job that goes on to
        // build another one.
        $this->spreadsheet->disconnectWorksheets();

        return $contents;
    }

    private function columnLetter(int $position): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, $position));
    }
}
