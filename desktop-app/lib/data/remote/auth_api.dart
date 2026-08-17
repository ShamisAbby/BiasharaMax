import '../../core/api/api_client.dart';
import '../../core/api/endpoints.dart';
import 'entitlement.dart';

class AuthenticatedUser {
  AuthenticatedUser({
    required this.id,
    required this.name,
    required this.email,
    required this.businessId,
    this.branchId,
    this.roleId,
  });

  factory AuthenticatedUser.fromJson(Map<String, dynamic> json) {
    return AuthenticatedUser(
      id: json['id'] as String,
      name: json['name'] as String,
      email: json['email'] as String,
      businessId: json['business_id'] as String,
      branchId: json['branch_id'] as String?,
      roleId: json['role_id'] as String?,
    );
  }

  final String id;
  final String name;
  final String email;
  final String businessId;
  final String? branchId;
  final String? roleId;
}

class LoginResult {
  LoginResult({required this.token, required this.user, required this.entitlement});

  final String token;
  final AuthenticatedUser user;

  /// Comes back with the login response so the app knows whether to show
  /// the dashboard or the activation step without a second round trip —
  /// and so a lapsed business is told why on the screen after sign-in
  /// rather than by a dashboard that fails on first use.
  final Entitlement entitlement;
}

/// Talks to AuthController — see app/Http/Controllers/Api/AuthController.php.
/// Separate from LicenseApi on purpose: this authenticates an employee,
/// not the installation itself.
class AuthApi {
  AuthApi(this._client);

  final ApiClient _client;

  Future<LoginResult> login({
    required String email,
    required String password,
    required String deviceName,
    String? deviceFingerprint,
  }) async {
    final response = await _client.post(Endpoints.authLogin, data: {
      'email': email,
      'password': password,
      'device_name': deviceName,
      // Lets the server judge device licensing for *this* machine in the
      // same call, so a licensed business is not sent to the dashboard
      // and bounced back out on the first request that checks.
      'device_fingerprint': deviceFingerprint,
    });

    final data = response.data as Map<String, dynamic>;

    return LoginResult(
      token: data['token'] as String,
      user: AuthenticatedUser.fromJson(data['user'] as Map<String, dynamic>),
      entitlement: Entitlement.fromJson(data['entitlement'] as Map<String, dynamic>),
    );
  }

  Future<void> logout() => _client.post(Endpoints.authLogout);

  /// Re-checks entitlement for an existing session, e.g. on app launch.
  Future<Entitlement> entitlement({String? deviceFingerprint}) async {
    final response = await _client.get(
      Endpoints.entitlement,
      query: {if (deviceFingerprint != null) 'device_fingerprint': deviceFingerprint},
    );

    final data = response.data as Map<String, dynamic>;

    return Entitlement.fromJson(data['entitlement'] as Map<String, dynamic>);
  }
}
