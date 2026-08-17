import '../../core/api/api_client.dart';
import '../../core/hardware/device_fingerprint.dart';
import '../../core/storage/secure_storage.dart';
import '../remote/auth_api.dart';
import '../remote/entitlement.dart';
import '../remote/license_api.dart';
import '../remote/registration_api.dart';

/// Where the app should open.
///
/// One enum rather than a chain of booleans scattered through the router,
/// because the ordering is the feature: intro, then who you are, then
/// whether you may come in, then the app. Getting that order wrong is how
/// the previous build asked for a product key before it knew who was
/// asking — and since product keys are only ever issued by hand from the
/// platform admin, a business that signed up for a trial had nothing to
/// type and could not use the desktop app at all.
enum StartupDestination {
  intro,
  auth,
  activation,
  dashboard,
}

/// The launch decision, with the I/O taken out so it can be tested.
///
/// [entitlement] is null when it was not asked for — which happens when
/// there is no session to ask about, not when the answer was "no".
StartupDestination startupDestinationFor({
  required bool introSeen,
  required bool hasSession,
  required Entitlement? entitlement,
}) {
  if (!introSeen) {
    return StartupDestination.intro;
  }

  if (!hasSession) {
    return StartupDestination.auth;
  }

  // Unreachable server, existing session: the app opens.
  //
  // A till that will not sell because the internet is down is worse than
  // useless, and the POS screen is offline-capable by design. Treating
  // "could not ask" as "not allowed" would shut a shop every time its
  // connection dropped.
  if (entitlement == null || entitlement.isUnknown) {
    return StartupDestination.dashboard;
  }

  return entitlement.allowed ? StartupDestination.dashboard : StartupDestination.activation;
}

class AuthRepository {
  AuthRepository({
    required AuthApi authApi,
    required LicenseApi licenseApi,
    required RegistrationApi registrationApi,
    required SecureStorage storage,
    required DeviceFingerprint fingerprint,
  })  : _authApi = authApi,
        _licenseApi = licenseApi,
        _registrationApi = registrationApi,
        _storage = storage,
        _fingerprint = fingerprint;

  final AuthApi _authApi;
  final LicenseApi _licenseApi;
  final RegistrationApi _registrationApi;
  final SecureStorage _storage;
  final DeviceFingerprint _fingerprint;

  /// Cached from the last successful sign-in or entitlement check, so the
  /// dashboard can show the plan and trial countdown without asking again
  /// on every rebuild.
  Entitlement? _entitlement;

  Entitlement? get entitlement => _entitlement;

  Future<bool> hasActivatedDevice() async => await _storage.getDeviceId() != null;

  Future<bool> hasActiveSession() async => await _storage.getToken() != null;

  /// The single decision the router asks on launch. Gathers the three
  /// inputs, then defers to [startupDestinationFor] for the choice.
  Future<StartupDestination> decideStartup() async {
    final introSeen = await _storage.hasSeenIntro();
    final hasSession = await hasActiveSession();

    return startupDestinationFor(
      introSeen: introSeen,
      hasSession: hasSession,
      // Not asked for when there is no session — an unauthenticated call
      // can only come back 401, and the answer would not change where the
      // user goes.
      entitlement: hasSession && introSeen ? await refreshEntitlement() : null,
    );
  }

  /// Best-effort. A network failure yields [Entitlement.unknown] rather
  /// than throwing, so callers can distinguish "we could not ask" from
  /// "you are not allowed" — telling a shop with a flaky connection that
  /// its subscription has expired would be a lie the app cannot support.
  Future<Entitlement> refreshEntitlement() async {
    try {
      final fingerprint = await _fingerprint.get();
      _entitlement = await _authApi.entitlement(deviceFingerprint: fingerprint);
    } on ApiException catch (e) {
      if (!e.isNetworkError) {
        rethrow;
      }

      _entitlement = Entitlement.unknown;
    }

    return _entitlement!;
  }

  Future<void> completeIntro() => _storage.markIntroSeen();

  Future<RegistrationOptions> registrationOptions() => _registrationApi.options();

  /// Creates the business and signs the new owner in, in one call.
  Future<Entitlement> register({
    required String ownerName,
    required String ownerEmail,
    required String password,
    required String passwordConfirmation,
    required String businessName,
    required String businessType,
    required String country,
    required String currency,
    String? ownerPhone,
    String? registrationCode,
  }) async {
    final result = await _registrationApi.register(
      ownerName: ownerName,
      ownerEmail: ownerEmail,
      password: password,
      passwordConfirmation: passwordConfirmation,
      businessName: businessName,
      businessType: businessType,
      country: country,
      currency: currency,
      ownerPhone: ownerPhone,
      registrationCode: registrationCode,
      deviceName: _fingerprint.machineName(),
    );

    await _persistSession(result.token, result.user);
    _entitlement = result.entitlement;

    return result.entitlement;
  }

  /// Activates this installation against a product key.
  ///
  /// Still here, and still meaningful — but it is no longer a gate in
  /// front of the login screen. It applies to businesses that hold
  /// licences, which the server decides (see DesktopEntitlementService).
  Future<Entitlement> activate(String licenseKey) async {
    final hardwareFingerprint = await _fingerprint.get();

    final result = await _licenseApi.activate(
      licenseKey: licenseKey,
      hardwareFingerprint: hardwareFingerprint,
      machineName: _fingerprint.machineName(),
    );

    if (!result.activated) {
      throw ApiException(message: result.reason ?? 'That product key could not be activated.');
    }

    await _storage.setLicenseKey(licenseKey);
    await _storage.setDeviceId(result.deviceId!);

    // Asked again rather than assumed: activating a device does not by
    // itself mean the subscription behind it is in good standing.
    return refreshEntitlement();
  }

  Future<AuthenticatedUser> login({required String email, required String password}) async {
    final result = await _authApi.login(
      email: email,
      password: password,
      deviceName: _fingerprint.machineName(),
      deviceFingerprint: await _fingerprint.get(),
    );

    await _persistSession(result.token, result.user);
    _entitlement = result.entitlement;

    return result.user;
  }

  Future<void> _persistSession(String token, AuthenticatedUser user) async {
    await _storage.setToken(token);

    if (user.branchId != null) {
      await _storage.setActiveBranchId(user.branchId!);
    }
  }

  /// Logs the employee out (server-side token revoked, local session
  /// cleared) but leaves the device activation in place — the till stays
  /// licensed for the next person to log in.
  Future<void> logout() async {
    try {
      await _authApi.logout();
    } finally {
      await _storage.clearSession();
      _entitlement = null;
    }
  }

  /// Periodic re-check while the app is running — catches the license
  /// being suspended/revoked or this device being deactivated remotely.
  /// Best-effort: a network failure here should never lock out a cashier
  /// who's mid-sale, so callers should treat exceptions as "skip, try
  /// again later", not "log the user out".
  Future<bool> revalidateLicense() async {
    final licenseKey = await _storage.getLicenseKey();

    if (licenseKey == null) {
      return false;
    }

    final fingerprint = await _fingerprint.get();

    return _licenseApi.validate(licenseKey: licenseKey, hardwareFingerprint: fingerprint);
  }
}
