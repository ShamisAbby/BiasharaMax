<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Platform\Http\Resources\AuditLogResource;
use App\Domain\Shared\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = $this->filteredQuery($request)
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Platform/AuditLogs/Index', [
            'logs' => AuditLogResource::collection($logs),
            'filters' => $request->only(['search', 'action', 'actor_type', 'module', 'risk_level']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $logs = $this->filteredQuery($request)->latest('created_at')->limit(5000)->get();

        return ResponseFacade::streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Module', 'Action', 'Actor Type', 'Actor ID', 'Auditable', 'Business', 'IP', 'Browser', 'OS', 'Device', 'Risk Level']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at->toDateTimeString(),
                    $log->module,
                    $log->action,
                    $log->actor_type,
                    $log->actor_id,
                    $log->auditable_type ? class_basename($log->auditable_type) : null,
                    $log->business?->name,
                    $log->ip_address,
                    $log->browser,
                    $log->operating_system,
                    $log->device_type,
                    $log->risk_level,
                ]);
            }

            fclose($handle);
        }, 'audit-logs.csv', ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery(Request $request)
    {
        return AuditLog::query()
            ->with('business')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('actor_type'), fn ($query) => $query->where('actor_type', $request->string('actor_type')))
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->string('module')))
            ->when($request->filled('risk_level'), fn ($query) => $query->where('risk_level', $request->string('risk_level')))
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(function ($q) use ($search) {
                    $q->where('auditable_type', 'like', "%{$search}%")
                        ->orWhereHas('business', fn ($b) => $b->where('name', 'like', "%{$search}%"));
                });
            });
    }
}
