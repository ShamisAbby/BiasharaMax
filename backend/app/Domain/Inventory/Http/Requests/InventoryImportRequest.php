<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Inventory\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', Product::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /*
             * `mimes` checks the guessed MIME type, not just the
             * extension — which matters because spreadsheets are
             * routinely mislabelled. A file saved from Excel as CSV is
             * frequently detected as `text/plain`, hence `txt`.
             *
             * CSV stays accepted alongside the spreadsheet formats: a
             * business arriving from another till almost always has CSV
             * to hand, and the reader treats all three identically.
             */
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload an Excel file (.xlsx or .xls) or a .csv. Download the template if you are not sure of the format.',
            'file.max' => 'That file is larger than 10 MB. Split it into smaller files and import them one at a time.',
        ];
    }
}
