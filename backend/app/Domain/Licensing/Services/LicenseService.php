<?php

namespace App\Domain\Licensing\Services;

use App\Domain\Licensing\Exceptions\LicenseException;
use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Models\LicenseActivationLog;
use App\Domain\Licensing\Models\LicenseDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything here is real and fully functional on the server side. What
 * doesn't exist yet is a desktop client to call it — there's no
 * Electron/Tauri app in this codebase (see docs: Desktop Edition is its
 * own future sprint). activate()/validate() accept a hardware fingerprint
 * as a plain string parameter; a real client would compute that fingerprint
 * itself and send it here. We can't fabricate that capture step without a
 * client to capture it from.
 */
class LicenseService
{
    /**
     * @param  array{
     *     business_id: string,
     *     type: string,
     *     max_devices?: int,
     *     expires_at?: ?Carbon,
     *     maintenance_expires_at?: ?Carbon,
     *     offline_activation_allowed?: bool,
     *     cloud_sync_enabled?: bool,
     *     notes?: ?string,
     *     created_by?: ?string,
     * }  $data
     */
    public function generate(array $data): License
    {
        return DB::transaction(function () use ($data) {
            $license = License::query()->create([
                'business_id' => $data['business_id'],
                'license_key' => $this->generateUniqueKey(),
                'type' => $data['type'],
                'max_devices' => $data['max_devices'] ?? 1,
                'status' => License::STATUS_ACTIVE,
                'issued_at' => Carbon::now(),
                'expires_at' => $data['expires_at'] ?? null,
                'maintenance_expires_at' => $data['maintenance_expires_at'] ?? null,
                'offline_activation_allowed' => $data['offline_activation_allowed'] ?? true,
                'cloud_sync_enabled' => $data['cloud_sync_enabled'] ?? false,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $this->log($license, null, LicenseActivationLog::ACTION_GENERATED, LicenseActivationLog::RESULT_SUCCESS);

            return $license;
        });
    }

    /**
     * The prefix on newly issued keys.
     *
     * Keys minted before the rename begin `BIOS-` and keep working: both
     * activation and validation look the key up by exact match (see
     * LicenseValidationController), so nothing anywhere parses this
     * prefix or checks the format. Two prefixes will be in circulation,
     * which is the normal cost of a rename and much cheaper than asking
     * every customer to re-activate their till.
     *
     * A constant rather than an inline string so the format lives in one
     * place if anything ever does need to recognise a key on sight.
     */
    public const KEY_PREFIX = 'BMAX';

    private function generateUniqueKey(): string
    {
        do {
            $segments = collect(range(1, 4))->map(
                fn () => Str::upper(Str::random(4)),
            )->implode('-');
            $key = self::KEY_PREFIX."-{$segments}";
        } while (License::query()->where('license_key', $key)->exists());

        return $key;
    }

    /**
     * @throws LicenseException
     */
    public function activate(License $license, string $hardwareFingerprint, ?string $machineName = null, ?string $ipAddress = null): LicenseDevice
    {
        return DB::transaction(function () use ($license, $hardwareFingerprint, $machineName, $ipAddress) {
            if (! $license->isUsable()) {
                $this->log($license, null, LicenseActivationLog::ACTION_ACTIVATED, LicenseActivationLog::RESULT_FAILURE, 'License is not active or has expired.');
                throw LicenseException::notUsable();
            }

            $existing = $license->devices()->where('hardware_fingerprint', $hardwareFingerprint)->first();

            if ($existing) {
                $existing->forceFill(['deactivated_at' => null, 'last_seen_at' => Carbon::now()])->save();
                $this->log($license, $existing, LicenseActivationLog::ACTION_ACTIVATED, LicenseActivationLog::RESULT_SUCCESS, 're-activated existing device');

                return $existing;
            }

            if (! $license->hasDeviceCapacity()) {
                $this->log($license, null, LicenseActivationLog::ACTION_ACTIVATED, LicenseActivationLog::RESULT_FAILURE, 'Device limit reached.');
                throw LicenseException::deviceLimitReached($license->max_devices);
            }

            $device = $license->devices()->create([
                'hardware_fingerprint' => $hardwareFingerprint,
                'machine_name' => $machineName,
                'ip_address' => $ipAddress,
                'activated_at' => Carbon::now(),
                'last_seen_at' => Carbon::now(),
            ]);

            $this->log($license, $device, LicenseActivationLog::ACTION_ACTIVATED, LicenseActivationLog::RESULT_SUCCESS);

            return $device;
        });
    }

    /**
     * @throws LicenseException
     */
    public function deactivateDevice(License $license, LicenseDevice $device): void
    {
        if ($device->license_id !== $license->id) {
            throw LicenseException::deviceNotFound();
        }

        $device->forceFill(['deactivated_at' => Carbon::now()])->save();

        $this->log($license, $device, LicenseActivationLog::ACTION_DEACTIVATED, LicenseActivationLog::RESULT_SUCCESS);
    }

    /**
     * Clear every device activation, freeing the license up for fresh
     * activations — e.g. after the customer replaces all their hardware.
     */
    public function resetActivation(License $license): void
    {
        $license->devices()->whereNull('deactivated_at')->update(['deactivated_at' => Carbon::now()]);

        $this->log($license, null, LicenseActivationLog::ACTION_RESET, LicenseActivationLog::RESULT_SUCCESS);
    }

    /**
     * Online validation — what a desktop client calls (with network
     * access) to confirm a license + device pairing is still good.
     */
    public function validate(string $licenseKey, string $hardwareFingerprint): array
    {
        $license = License::query()->where('license_key', $licenseKey)->first();

        if ($license === null) {
            return ['valid' => false, 'reason' => 'License key not found.'];
        }

        if (! $license->isUsable()) {
            $this->log($license, null, LicenseActivationLog::ACTION_VALIDATED, LicenseActivationLog::RESULT_FAILURE, 'License not usable.');

            return ['valid' => false, 'reason' => 'License is not active or has expired.'];
        }

        $device = $license->devices()->where('hardware_fingerprint', $hardwareFingerprint)->whereNull('deactivated_at')->first();

        if ($device === null) {
            $this->log($license, null, LicenseActivationLog::ACTION_VALIDATED, LicenseActivationLog::RESULT_FAILURE, 'Device not activated.');

            return ['valid' => false, 'reason' => 'This device is not activated for this license.'];
        }

        $device->forceFill(['last_seen_at' => Carbon::now()])->save();
        $this->log($license, $device, LicenseActivationLog::ACTION_VALIDATED, LicenseActivationLog::RESULT_SUCCESS);

        return [
            'valid' => true,
            'type' => $license->type,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'maintenance_active' => $license->isMaintenanceActive(),
        ];
    }

    public function renew(License $license, ?Carbon $newExpiresAt, ?Carbon $newMaintenanceExpiresAt = null): License
    {
        $license->forceFill([
            'status' => License::STATUS_ACTIVE,
            'expires_at' => $newExpiresAt,
            'maintenance_expires_at' => $newMaintenanceExpiresAt ?? $license->maintenance_expires_at,
        ])->save();

        $this->log($license, null, LicenseActivationLog::ACTION_RENEWED, LicenseActivationLog::RESULT_SUCCESS);

        return $license->refresh();
    }

    public function suspend(License $license): License
    {
        $license->forceFill(['status' => License::STATUS_SUSPENDED])->save();

        $this->log($license, null, LicenseActivationLog::ACTION_SUSPENDED, LicenseActivationLog::RESULT_SUCCESS);

        return $license->refresh();
    }

    public function restore(License $license): License
    {
        $license->forceFill(['status' => License::STATUS_ACTIVE])->save();

        $this->log($license, null, LicenseActivationLog::ACTION_RESTORED, LicenseActivationLog::RESULT_SUCCESS);

        return $license->refresh();
    }

    public function revoke(License $license, string $reason): License
    {
        $license->forceFill([
            'status' => License::STATUS_REVOKED,
            'revoked_at' => Carbon::now(),
            'revoked_reason' => $reason,
        ])->save();

        $license->devices()->whereNull('deactivated_at')->update(['deactivated_at' => Carbon::now()]);

        $this->log($license, null, LicenseActivationLog::ACTION_REVOKED, LicenseActivationLog::RESULT_SUCCESS, $reason);

        return $license->refresh();
    }

    private function log(License $license, ?LicenseDevice $device, string $action, string $result, ?string $reason = null): void
    {
        LicenseActivationLog::query()->create([
            'license_id' => $license->id,
            'license_device_id' => $device?->id,
            'action' => $action,
            'result' => $result,
            'reason' => $reason,
            'ip_address' => request()?->ip(),
        ]);
    }
}
