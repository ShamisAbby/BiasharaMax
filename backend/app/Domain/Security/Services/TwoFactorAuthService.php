<?php

namespace App\Domain\Security\Services;

use App\Domain\Security\Models\TwoFactorCredential;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthService
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function setUp(string $authenticatableType, string $authenticatableId, string $accountName): TwoFactorCredential
    {
        $secret = $this->google2fa->generateSecretKey();

        return TwoFactorCredential::query()->updateOrCreate(
            ['authenticatable_type' => $authenticatableType, 'authenticatable_id' => $authenticatableId],
            ['secret' => $secret, 'recovery_codes' => null, 'confirmed_at' => null, 'enabled_at' => null],
        );
    }

    public function qrCodeUrl(TwoFactorCredential $credential, string $accountName, string $companyName = 'BiasharaMax'): string
    {
        return $this->google2fa->getQRCodeUrl($companyName, $accountName, $credential->secret);
    }

    public function confirm(TwoFactorCredential $credential, string $oneTimePassword): bool
    {
        if (! $this->google2fa->verifyKey($credential->secret, $oneTimePassword)) {
            return false;
        }

        $credential->update([
            'confirmed_at' => now(),
            'enabled_at' => now(),
            'recovery_codes' => $this->generateRecoveryCodes(),
        ]);

        return true;
    }

    public function verify(TwoFactorCredential $credential, string $oneTimePassword): bool
    {
        if (! $credential->isEnabled()) {
            return false;
        }

        if ($this->google2fa->verifyKey($credential->secret, $oneTimePassword)) {
            return true;
        }

        return $this->consumeRecoveryCode($credential, $oneTimePassword);
    }

    public function disable(TwoFactorCredential $credential): void
    {
        $credential->delete();
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)))
            ->all();
    }

    private function consumeRecoveryCode(TwoFactorCredential $credential, string $code): bool
    {
        $codes = $credential->recovery_codes ?? [];

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $credential->update(['recovery_codes' => array_values(array_diff($codes, [$code]))]);

        return true;
    }
}
