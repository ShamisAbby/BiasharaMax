/// Runtime configuration for the desktop client.
///
/// A shop's server isn't always the same place: it might be BiasharaMax
/// Cloud (`https://<business>.biasharamax.com`), a self-hosted box on the
/// shop's own LAN, or (for `offline_activation_allowed` licenses) a server
/// that's only reachable intermittently. The base URL is therefore a
/// runtime setting the cashier/owner enters once during activation and
/// which is then persisted locally — never hard-coded here.
class AppConfig {
  AppConfig({required String apiBaseUrl}) : apiBaseUrl = normaliseBaseUrl(apiBaseUrl);

  /// Always ends with exactly one `/`. See [normaliseBaseUrl].
  final String apiBaseUrl;

  /// Used only until the real value is loaded from secure storage on
  /// first run (see `SecureStorage.getApiBaseUrl`).
  ///
  /// Note: this points at the `/api` prefix Laravel adds automatically for
  /// routes/api.php — endpoint paths (see `Endpoints`) already include the
  /// `v1/...` segment on top of this, matching the `v1/...` route group
  /// prefixes defined server-side.
  static final fallback = AppConfig(apiBaseUrl: 'http://localhost:8000/api');

  /// Guarantees a single trailing slash.
  ///
  /// Dio joins `baseUrl` and path by plain string concatenation — it does
  /// not insert a separator. So a base of `…:8000/api` and a path of
  /// `v1/licenses/activate` produce:
  ///
  ///     http://localhost:8000/apiv1/licenses/activate
  ///
  /// which is what the till reported as *"The route apiv1/licenses/activate
  /// could not be found"* on first run.
  ///
  /// This is normalised here rather than fixed by adding a slash to the
  /// default, because the default is not where the value usually comes
  /// from. The base URL is typed by an owner during activation — Cloud, a
  /// LAN box, whatever — and requiring them to get a trailing slash right
  /// is a support ticket waiting to happen. Both forms now work, as does a
  /// value with stray whitespace from a copy-paste.
  static String normaliseBaseUrl(String raw) {
    final trimmed = raw.trim();

    if (trimmed.isEmpty) return trimmed;

    // A regex rather than a single `endsWith`, so `…/api///` collapses too
    // — pasted URLs pick up odd tails.
    return '${trimmed.replaceAll(RegExp(r'/+$'), '')}/';
  }
}
