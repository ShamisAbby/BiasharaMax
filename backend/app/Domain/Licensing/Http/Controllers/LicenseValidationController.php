<?php

namespace App\Domain\Licensing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Licensing\Exceptions\LicenseException;
use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The unauthenticated REST surface a desktop client calls — no Sanctum
 * session exists when a license is being activated from an installer,
 * so this validates by license key + hardware fingerprint instead.
 */
class LicenseValidationController extends Controller
{
    public function activate(Request $request, LicenseService $licenses): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string'],
            'hardware_fingerprint' => ['required', 'string'],
            'machine_name' => ['nullable', 'string', 'max:255'],
        ]);

        $license = License::query()->where('license_key', $validated['license_key'])->first();

        if ($license === null) {
            return response()->json(['activated' => false, 'reason' => 'License key not found.'], 404);
        }

        try {
            $device = $licenses->activate(
                $license,
                $validated['hardware_fingerprint'],
                $validated['machine_name'] ?? null,
                $request->ip(),
            );
        } catch (LicenseException $e) {
            return response()->json(['activated' => false, 'reason' => $e->getMessage()], 422);
        }

        return response()->json([
            'activated' => true,
            'device_id' => $device->id,
            'license_type' => $license->type,
            'expires_at' => $license->expires_at?->toIso8601String(),
        ]);
    }

    public function validateLicense(Request $request, LicenseService $licenses): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string'],
            'hardware_fingerprint' => ['required', 'string'],
        ]);

        $result = $licenses->validate($validated['license_key'], $validated['hardware_fingerprint']);

        return response()->json($result, $result['valid'] ? 200 : 422);
    }
}
