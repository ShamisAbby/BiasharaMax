import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Everything here is small, sensitive, and rarely-changing — the Sanctum
/// token, the activated license key, the server's base URL, and this
/// installation's stable device id. Bulk business data (products, sales,
/// the outbox) lives in the Drift database instead, not here.
class SecureStorage {
  SecureStorage(this._storage);

  /// Construct with the platform options this app needs, so no caller has
  /// to remember them. See [macOsOptions] for why macOS is special.
  factory SecureStorage.withDefaults() =>
      SecureStorage(const FlutterSecureStorage(mOptions: macOsOptions));

  /// macOS has two Keychain implementations and the package picks the
  /// wrong one for this app by default.
  ///
  /// `useDataProtectionKeyChain` defaults to **true**, selecting the modern
  /// data-protection Keychain. That one requires the app to be signed with
  /// a `keychain-access-groups` entitlement, which in turn requires a
  /// development certificate. Without it every read and write fails with
  /// `-34018` / `errSecMissingEntitlement` — and because the Keychain
  /// refuses the call at the API boundary rather than returning an error
  /// code, it arrives as a raw `PlatformException` the app cannot handle.
  ///
  /// Note this is *not* about the App Sandbox. Turning the sandbox off does
  /// not help; the requirement comes from the Keychain API itself.
  ///
  /// `false` selects the legacy file-based Keychain, which an ad-hoc signed
  /// app can use. What that costs: no iCloud Keychain sync, which this app
  /// has no use for — a till's auth token and chosen warehouse are
  /// per-device by definition and syncing them across a user's Macs would
  /// be wrong, not merely unnecessary.
  static const macOsOptions = MacOsOptions(useDataProtectionKeyChain: false);

  final FlutterSecureStorage _storage;

  static const _keyToken = 'auth_token';
  static const _keyLicenseKey = 'license_key';
  static const _keyDeviceId = 'device_id';
  static const _keyDeviceFingerprint = 'device_fingerprint';
  static const _keyApiBaseUrl = 'api_base_url';
  static const _keyActiveWarehouseId = 'active_warehouse_id';
  static const _keyActiveBranchId = 'active_branch_id';
  static const _keyIntroSeen = 'intro_seen';

  Future<String?> getToken() => _storage.read(key: _keyToken);

  Future<void> setToken(String token) => _storage.write(key: _keyToken, value: token);

  Future<void> clearToken() => _storage.delete(key: _keyToken);

  Future<String?> getLicenseKey() => _storage.read(key: _keyLicenseKey);

  Future<void> setLicenseKey(String key) => _storage.write(key: _keyLicenseKey, value: key);

  Future<String?> getDeviceId() => _storage.read(key: _keyDeviceId);

  Future<void> setDeviceId(String id) => _storage.write(key: _keyDeviceId, value: id);

  Future<String?> getDeviceFingerprint() => _storage.read(key: _keyDeviceFingerprint);

  Future<void> setDeviceFingerprint(String fingerprint) =>
      _storage.write(key: _keyDeviceFingerprint, value: fingerprint);

  Future<String?> getApiBaseUrl() => _storage.read(key: _keyApiBaseUrl);

  Future<void> setApiBaseUrl(String url) => _storage.write(key: _keyApiBaseUrl, value: url);

  /// The till's chosen warehouse — a business's branch can own more than
  /// one warehouse, and `sales.warehouse_id` needs exactly one, so this is
  /// asked once (see LocationScreen) rather than guessed.
  Future<String?> getActiveWarehouseId() => _storage.read(key: _keyActiveWarehouseId);

  Future<void> setActiveWarehouseId(String id) => _storage.write(key: _keyActiveWarehouseId, value: id);

  /// Defaults to the logged-in employee's own `branch_id` (see
  /// AuthenticatedUser) — most cashiers work at one branch — but stored
  /// separately in case a future multi-branch employee needs to switch.
  Future<String?> getActiveBranchId() => _storage.read(key: _keyActiveBranchId);

  Future<void> setActiveBranchId(String id) => _storage.write(key: _keyActiveBranchId, value: id);

  /// Whether the three intro screens have already been through.
  ///
  /// Stored per install, not per user: it explains what the product is,
  /// which the second person to sign in on the same till does not need
  /// to sit through again.
  Future<bool> hasSeenIntro() async => await _storage.read(key: _keyIntroSeen) == 'true';

  Future<void> markIntroSeen() => _storage.write(key: _keyIntroSeen, value: 'true');

  /// True once both a license has been activated on this device and an
  /// employee has logged in — the two gates the app must pass through
  /// before showing any business screen.
  Future<bool> isFullyActivated() async {
    final hasLicense = await getDeviceId() != null;
    final hasSession = await getToken() != null;

    return hasLicense && hasSession;
  }

  /// Logout clears the employee session only — the device stays licensed,
  /// so the next person can log in without re-activating the install.
  Future<void> clearSession() => clearToken();
}
