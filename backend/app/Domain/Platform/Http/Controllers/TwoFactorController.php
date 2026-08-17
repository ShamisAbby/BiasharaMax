<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Security\Models\TwoFactorCredential;
use App\Domain\Security\Services\TwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function setup(Request $request, TwoFactorAuthService $service): RedirectResponse
    {
        $credential = $service->setUp('platform_user', $request->user()->id, $request->user()->email);
        $qrCodeUrl = $service->qrCodeUrl($credential, $request->user()->email);

        return back()->with(['status' => '2fa-setup-started', 'qr_code_url' => $qrCodeUrl, 'secret' => $credential->secret]);
    }

    public function confirm(Request $request, TwoFactorAuthService $service): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        $credential = TwoFactorCredential::query()
            ->where('authenticatable_type', 'platform_user')
            ->where('authenticatable_id', $request->user()->id)
            ->firstOrFail();

        if (! $service->confirm($credential, $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        return back()->with(['status' => '2fa-enabled', 'recovery_codes' => $credential->fresh()->recovery_codes]);
    }

    public function disable(Request $request, TwoFactorAuthService $service): RedirectResponse
    {
        $credential = TwoFactorCredential::query()
            ->where('authenticatable_type', 'platform_user')
            ->where('authenticatable_id', $request->user()->id)
            ->first();

        if ($credential) {
            $service->disable($credential);
        }

        return back()->with('status', '2fa-disabled');
    }
}
