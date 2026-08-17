import '../../core/api/api_client.dart';
import '../../core/api/endpoints.dart';

class LicenseActivationResult {
  LicenseActivationResult({
    required this.activated,
    this.deviceId,
    this.licenseType,
    this.expiresAt,
    this.reason,
  });

  factory LicenseActivationResult.fromJson(Map<String, dynamic> json) {
    return LicenseActivationResult(
      activated: json['activated'] == true,
      deviceId: json['device_id'] as String?,
      licenseType: json['license_type'] as String?,
      expiresAt: json['expires_at'] as String?,
      reason: json['reason'] as String?,
    );
  }

  final bool activated;
  final String? deviceId;
  final String? licenseType;
  final String? expiresAt;
  final String? reason;
}

/// Talks to LicenseValidationController — see
/// app/Modules/Licensing/Http/Controllers/LicenseValidationController.php.
/// Unauthenticated by design: there's no employee session yet at the point
/// a fresh install is being activated.
class LicenseApi {
  LicenseApi(this._client);

  final ApiClient _client;

  Future<LicenseActivationResult> activate({
    required String licenseKey,
    required String hardwareFingerprint,
    String? machineName,
  }) async {
    final response = await _client.post(Endpoints.licenseActivate, data: {
      'license_key': licenseKey,
      'hardware_fingerprint': hardwareFingerprint,
      'machine_name': machineName,
    });

    return LicenseActivationResult.fromJson(response.data as Map<String, dynamic>);
  }

  /// Periodic re-validation while the app is running (e.g. once a day,
  /// whenever there's connectivity) — catches a license being suspended,
  /// revoked, or this device being deactivated remotely by the owner.
  Future<bool> validate({required String licenseKey, required String hardwareFingerprint}) async {
    final response = await _client.post(Endpoints.licenseValidate, data: {
      'license_key': licenseKey,
      'hardware_fingerprint': hardwareFingerprint,
    });

    final data = response.data as Map<String, dynamic>;

    return data['valid'] == true;
  }
}
