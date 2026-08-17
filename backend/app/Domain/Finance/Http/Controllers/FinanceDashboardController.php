<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Services\FinanceDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceDashboardController extends Controller
{
    public function __invoke(Request $request, FinanceDashboardService $service): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        return Inertia::render('Finance/Dashboard', [
            'summary' => $service->summary($request->user()->business_id),
        ]);
    }
}
