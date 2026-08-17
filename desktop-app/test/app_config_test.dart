import 'package:biasharamax_desktop/core/api/endpoints.dart';
import 'package:biasharamax_desktop/core/config/app_config.dart';
import 'package:flutter_test/flutter_test.dart';

/// Dio concatenates `baseUrl` and path without inserting a separator, so a
/// base URL missing its trailing slash produces `…/apiv1/licenses/activate`
/// and every single request 404s. That was the first-run failure, and it is
/// the kind of thing that regresses quietly the next time someone edits a
/// default string.
void main() {
  group('normaliseBaseUrl', () {
    test('adds a missing trailing slash', () {
      expect(
        AppConfig.normaliseBaseUrl('http://localhost:8000/api'),
        'http://localhost:8000/api/',
      );
    });

    test('leaves an existing trailing slash alone', () {
      expect(
        AppConfig.normaliseBaseUrl('http://localhost:8000/api/'),
        'http://localhost:8000/api/',
      );
    });

    test('collapses a pasted tail of several slashes', () {
      expect(
        AppConfig.normaliseBaseUrl('https://shop.biasharamax.com/api///'),
        'https://shop.biasharamax.com/api/',
      );
    });

    test('trims whitespace, which copy-paste adds and nobody sees', () {
      expect(
        AppConfig.normaliseBaseUrl('  https://shop.biasharamax.com/api  '),
        'https://shop.biasharamax.com/api/',
      );
    });

    test('leaves an empty value empty rather than inventing a slash', () {
      expect(AppConfig.normaliseBaseUrl(''), '');
      expect(AppConfig.normaliseBaseUrl('   '), '');
    });
  });

  group('AppConfig', () {
    test('normalises whatever it is constructed with', () {
      expect(
        AppConfig(apiBaseUrl: 'http://192.168.1.50:8000/api').apiBaseUrl,
        'http://192.168.1.50:8000/api/',
      );
    });

    test('the built-in fallback is already well formed', () {
      expect(AppConfig.fallback.apiBaseUrl, endsWith('/'));
    });

    /// The assertion that actually mirrors the bug: base + endpoint, joined
    /// the way Dio joins them, has to be a real route.
    test('joins with an endpoint to give a valid path', () {
      final joined = AppConfig.fallback.apiBaseUrl + Endpoints.licenseActivate;

      expect(joined, 'http://localhost:8000/api/v1/licenses/activate');
      expect(joined, isNot(contains('apiv1')));
    });
  });
}
