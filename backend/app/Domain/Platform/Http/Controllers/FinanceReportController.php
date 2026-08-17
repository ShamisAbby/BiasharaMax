<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Services\FinanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    public function index(Request $request, FinanceReportService $reports): Response
    {
        $key = $request->string('report', 'revenue')->value();
        $filters = $request->only(['date_from', 'date_to']);

        return Inertia::render('Platform/Finance/Reports/Index', [
            'catalog' => $reports->catalog(),
            'selectedReport' => $key,
            'filters' => $filters,
            'report' => $reports->generate($key, $filters),
        ]);
    }

    public function exportCsv(Request $request, FinanceReportService $reports): StreamedResponse
    {
        $key = $request->string('report', 'revenue')->value();
        $data = $reports->generate($key, $request->only(['date_from', 'date_to']));

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $data['columns']);

            foreach ($data['rows'] as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, "{$key}-report.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Request $request, FinanceReportService $reports): HttpResponse
    {
        $key = $request->string('report', 'revenue')->value();
        $label = collect($reports->catalog())->firstWhere('key', $key)['label'] ?? ucfirst($key);
        $data = $reports->generate($key, $request->only(['date_from', 'date_to']));

        $pdf = Pdf::loadView('reports.finance-export', [
            'title' => $label,
            'columns' => $data['columns'],
            'rows' => $data['rows'],
            'summary' => $data['summary'] ?? null,
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        return $pdf->download("{$key}-report.pdf");
    }
}
