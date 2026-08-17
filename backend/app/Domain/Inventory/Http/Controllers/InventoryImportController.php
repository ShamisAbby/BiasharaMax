<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Http\Requests\InventoryImportRequest;
use App\Domain\Inventory\Jobs\ProcessInventoryImport;
use App\Domain\Inventory\Models\InventoryImportLog;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\InventoryExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryImportController extends Controller
{
    public function __construct(
        private readonly InventoryExportService $exportService,
    ) {}

    public function store(InventoryImportRequest $request): RedirectResponse
    {
        $path = $request->file('file')->store('imports', 'local');

        $log = InventoryImportLog::create([
            'business_id' => $request->user()->business_id,
            'file_path' => $path,
            'status' => InventoryImportLog::STATUS_PROCESSING,
            'created_by' => $request->user()->id,
        ]);

        ProcessInventoryImport::dispatch($log);

        return back()->with('status', 'inventory-import-started');
    }

    public function export(): Response
    {
        $this->authorize('export', Product::class);

        return $this->spreadsheet(
            $this->exportService->exportXlsx(request()->user()->business_id),
            'products-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * A blank workbook with the expected headers and one example row.
     *
     * Not gated on the export permission: this contains no business data
     * at all, and needing permission to see the *shape* of an import
     * file would block the person preparing it from the person allowed
     * to run it.
     */
    public function template(): Response
    {
        return $this->spreadsheet(
            $this->exportService->templateXlsx(),
            'product-import-template.xlsx',
        );
    }

    private function spreadsheet(string $contents, string $filename): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            // Without this some browsers serve a stale copy after the
            // catalogue changes, and the vendor concludes the export is
            // broken rather than cached.
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function downloadErrorReport(InventoryImportLog $log): BinaryFileResponse
    {
        abort_unless($log->business_id === request()->user()->business_id, 404);
        abort_unless($log->error_report_path, 404);

        return Storage::download($log->error_report_path);
    }
}
