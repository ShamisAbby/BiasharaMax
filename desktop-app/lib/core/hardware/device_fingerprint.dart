import 'dart:io';

import 'package:uuid/uuid.dart';

import '../storage/secure_storage.dart';

/// Produces the `hardware_fingerprint` the backend's
/// `LicenseValidationController` expects (see
/// app/Modules/Licensing/Http/Controllers/LicenseValidationController.php).
///
/// Deliberately not doing low-level MAC address / BIOS UUID reads: those
/// need different native plugins per OS (Windows/macOS/Linux), can change
/// when a user swaps a network card, and mostly buy nothing over the
/// simpler approach here — a random id generated once and persisted in
/// secure storage. It's stable for the life of the install (which is what
/// "one activation per device" actually requires), survives hostname
/// changes, and needs no native platform channel code to maintain.
class DeviceFingerprint {
  DeviceFingerprint(this._storage);

  final SecureStorage _storage;

  Future<String> get() async {
    final existing = await _storage.getDeviceFingerprint();

    if (existing != null) {
      return existing;
    }

    final generated = '${Platform.operatingSystem}-${const Uuid().v4()}';
    await _storage.setDeviceFingerprint(generated);

    return generated;
  }

  /// A human-friendly label sent alongside the fingerprint so the license's
  /// device list (visible to the business owner / Super Admin) is
  /// something a person can actually recognize ("Front Counter PC" instead
  /// of a UUID).
  String machineName() {
    final host = Platform.localHostname;

    return host.isNotEmpty ? host : '${Platform.operatingSystem} device';
  }
}
