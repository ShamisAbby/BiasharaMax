<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Business;
use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Models\LicenseDevice;
use App\Domain\Licensing\Services\LicenseAnalyticsService;
use App\Domain\Licensing\Services\LicenseService;
use App\Domain\Licensing\Services\OfflineCertificateService;
use App\Domain\Platform\Http\Requests\GenerateLicenseRequest;
use App\Domain\Platform\Http\Resources\LicenseActivationLogResource;
use App\Domain\Platform\Http\Resources\LicenseDeviceResource;
use App\Domain\Platform\Http\Resources\LicenseResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class LicenseController extends Controller
{
    public function dashboard(LicenseAnalyticsService $analytics): \Inertia\Response
    {
        $dashboard = $analytics->dashboard();

        return Inertia::render('Platform/Licenses/Dashboard', [
            'overview' => $dashboard['overview'],
            'byType' => $dashboard['by_type'],
            'expiringSoon' => $dashboard['expiring_soon']->map(fn (License $license) => [
                'id' => $license->id,
                'license_key' => $license->license_key,
                'business_name' => $license->business?->name,
                'expires_at' => $license->expires_at,
            ]),
        ]);
    }

    public function index(Request $request): \Inertia\Response
    {
        $licenses = License::query()
            ->with('business')
            ->withCount('activeDevices')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(function ($q) use ($search) {
                    $q->where('license_key', 'like', "%{$search}%")
                        ->orWhereHas('business', fn ($b) => $b->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Platform/Licenses/Index', [
            'licenses' => LicenseResource::collection($licenses),
            'filters' => $request->only(['search', 'status', 'type']),
            'businesses' => Business::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(GenerateLicenseRequest $request, LicenseService $licenses): RedirectResponse
    {
        $validated = $request->validated();

        $licenses->generate([
            'business_id' => $validated['business_id'],
            'type' => $validated['type'],
            'max_devices' => $validated['max_devices'],
            'expires_at' => isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
            'maintenance_expires_at' => isset($validated['maintenance_expires_at']) ? Carbon::parse($validated['maintenance_expires_at']) : null,
            'offline_activation_allowed' => $validated['offline_activation_allowed'] ?? true,
            'cloud_sync_enabled' => $validated['cloud_sync_enabled'] ?? false,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'license-generated');
    }

    public function show(License $license): \Inertia\Response
    {
        $license->load(['business', 'devices' => fn ($q) => $q->orderByDesc('activated_at')]);

        $logs = $license->activationLogs()->with('device')->latest('created_at')->limit(50)->get();

        return Inertia::render('Platform/Licenses/Show', [
            'license' => new LicenseResource($license),
            'devices' => LicenseDeviceResource::collection($license->devices),
            'activationLogs' => LicenseActivationLogResource::collection($logs),
        ]);
    }

    public function renew(Request $request, License $license, LicenseService $licenses): RedirectResponse
    {
        $validated = $request->validate([
            'expires_at' => ['nullable', 'date'],
            'maintenance_expires_at' => ['nullable', 'date'],
        ]);

        $licenses->renew(
            $license,
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
            isset($validated['maintenance_expires_at']) ? Carbon::parse($validated['maintenance_expires_at']) : null,
        );

        return back()->with('status', 'license-renewed');
    }

    public function suspend(License $license, LicenseService $licenses): RedirectResponse
    {
        $licenses->suspend($license);

        return back()->with('status', 'license-suspended');
    }

    public function restore(License $license, LicenseService $licenses): RedirectResponse
    {
        $licenses->restore($license);

        return back()->with('status', 'license-restored');
    }

    public function revoke(Request $request, License $license, LicenseService $licenses): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $licenses->revoke($license, $validated['reason']);

        return back()->with('status', 'license-revoked');
    }

    public function resetActivation(License $license, LicenseService $licenses): RedirectResponse
    {
        $licenses->resetActivation($license);

        return back()->with('status', 'license-activation-reset');
    }

    public function deactivateDevice(License $license, LicenseDevice $device, LicenseService $licenses): RedirectResponse
    {
        $licenses->deactivateDevice($license, $device);

        return back()->with('status', 'device-deactivated');
    }

    public function downloadCertificate(License $license, OfflineCertificateService $certificates): Response
    {
        $certificate = $certificates->generateCertificate($license);

        return response($certificate, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$license->license_key}.lic\"",
        ]);
    }
}
