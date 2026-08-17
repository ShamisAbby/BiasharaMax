<?php

namespace Tests\Unit\Licensing;

use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Services\LicenseService;
use App\Domain\Licensing\Services\OfflineCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class OfflineCertificateServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private function makeLicense(): License
    {
        [, $business] = $this->createOwnerWithBusiness();

        return app(LicenseService::class)->generate([
            'business_id' => $business->id,
            'type' => License::TYPE_ENTERPRISE,
            'max_devices' => 5,
        ]);
    }

    public function test_a_genuine_certificate_verifies_successfully(): void
    {
        $license = $this->makeLicense();
        $service = app(OfflineCertificateService::class);

        $certificate = $service->generateCertificate($license);

        $this->assertTrue($service->verify($certificate));
    }

    public function test_a_tampered_certificate_fails_verification(): void
    {
        $license = $this->makeLicense();
        $service = app(OfflineCertificateService::class);

        $certificate = $service->generateCertificate($license);
        $tampered = substr($certificate, 0, -10).str_repeat('X', 10);

        $this->assertFalse($service->verify($tampered));
    }

    public function test_garbage_input_fails_verification_without_erroring(): void
    {
        $service = app(OfflineCertificateService::class);

        $this->assertFalse($service->verify('not-a-real-certificate'));
    }

    public function test_certificates_for_different_licenses_are_not_interchangeable(): void
    {
        $licenseOne = $this->makeLicense();
        $licenseTwo = $this->makeLicense();
        $service = app(OfflineCertificateService::class);

        $certificateOne = $service->generateCertificate($licenseOne);

        // The certificate is self-contained and signed; mutating which
        // license it "claims" to be for (without re-signing) must fail.
        $decoded = json_decode(base64_decode($certificateOne), true);
        $payload = json_decode(base64_decode($decoded['payload']), true);
        $payload['license_key'] = $licenseTwo->license_key;
        $forged = base64_encode(json_encode([
            'payload' => base64_encode(json_encode($payload)),
            'signature' => $decoded['signature'],
        ]));

        $this->assertFalse($service->verify($forged));
    }
}
