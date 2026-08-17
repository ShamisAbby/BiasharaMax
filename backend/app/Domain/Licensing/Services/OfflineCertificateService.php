<?php

namespace App\Domain\Licensing\Services;

use App\Domain\Licensing\Models\License;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a signed, downloadable offline activation certificate for a
 * license. This is real, working asymmetric cryptography (RSA-2048,
 * SHA-256) — the server signs with a private key it alone holds; a
 * desktop client would verify the signature with the matching public
 * key, entirely without network access. What's deferred is the desktop
 * client itself: nothing exists yet to embed that public key and act on
 * a verified certificate. verify() is included so this can be tested
 * end-to-end on the server; the real verification happens client-side.
 */
class OfflineCertificateService
{
    private const KEY_DISK = 'local';

    private const PRIVATE_KEY_PATH = 'licensing/private.pem';

    private const PUBLIC_KEY_PATH = 'licensing/public.pem';

    public function generateCertificate(License $license): string
    {
        $payload = [
            'license_key' => $license->license_key,
            'business_id' => $license->business_id,
            'type' => $license->type,
            'max_devices' => $license->max_devices,
            'issued_at' => $license->issued_at->toIso8601String(),
            'expires_at' => $license->expires_at?->toIso8601String(),
            'maintenance_expires_at' => $license->maintenance_expires_at?->toIso8601String(),
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->sign($json);

        return base64_encode(json_encode([
            'payload' => base64_encode($json),
            'signature' => base64_encode($signature),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Confirms a certificate's signature is genuine and matches the
     * license it claims to be for. A real desktop client runs the
     * equivalent of this with openssl_verify() and its embedded public
     * key, with no server round-trip.
     */
    public function verify(string $certificate): bool
    {
        $decoded = json_decode(base64_decode($certificate), true);

        if (! is_array($decoded) || ! isset($decoded['payload'], $decoded['signature'])) {
            return false;
        }

        $json = base64_decode($decoded['payload']);
        $signature = base64_decode($decoded['signature']);

        return openssl_verify($json, $signature, $this->publicKey(), OPENSSL_ALGO_SHA256) === 1;
    }

    public function publicKeyPem(): string
    {
        return $this->publicKey();
    }

    private function sign(string $data): string
    {
        $signature = '';
        openssl_sign($data, $signature, $this->privateKey(), OPENSSL_ALGO_SHA256);

        return $signature;
    }

    private function privateKey(): string
    {
        $this->ensureKeyPairExists();

        return Storage::disk(self::KEY_DISK)->get(self::PRIVATE_KEY_PATH);
    }

    private function publicKey(): string
    {
        $this->ensureKeyPairExists();

        return Storage::disk(self::KEY_DISK)->get(self::PUBLIC_KEY_PATH);
    }

    private function ensureKeyPairExists(): void
    {
        $disk = Storage::disk(self::KEY_DISK);

        if ($disk->exists(self::PRIVATE_KEY_PATH) && $disk->exists(self::PUBLIC_KEY_PATH)) {
            return;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privateKeyPem);
        $publicKeyPem = openssl_pkey_get_details($resource)['key'];

        $disk->put(self::PRIVATE_KEY_PATH, $privateKeyPem);
        $disk->put(self::PUBLIC_KEY_PATH, $publicKeyPem);
    }
}
