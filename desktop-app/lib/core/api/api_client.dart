import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../storage/secure_storage.dart';

/// Thin Dio wrapper: attaches the Sanctum bearer token to every request,
/// and normalizes Dio's exception into a small `ApiException` the rest of
/// the app can catch without knowing anything about Dio.
class ApiClient {
  ApiClient({required AppConfig config, required SecureStorage storage})
      : _storage = storage,
        _dio = Dio(BaseOptions(
          baseUrl: config.apiBaseUrl,
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 30),
          headers: {'Accept': 'application/json'},
        )) {
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.getToken();

        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }

        handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          // Token revoked/expired server-side (e.g. license deactivated,
          // or the employee was logged out remotely) — drop the local
          // session so the UI falls back to the login screen instead of
          // silently failing every subsequent request.
          await _storage.clearSession();
        }

        handler.next(error);
      },
    ));
  }

  final Dio _dio;
  final SecureStorage _storage;

  Future<Response<dynamic>> get(String path, {Map<String, dynamic>? query}) {
    return _guard(() => _dio.get(path, queryParameters: query));
  }

  Future<Response<dynamic>> post(String path, {Object? data}) {
    return _guard(() => _dio.post(path, data: data));
  }

  Future<Response<dynamic>> _guard(Future<Response<dynamic>> Function() call) async {
    try {
      return await call();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

/// A Dio-free exception shape so screens and the sync manager can pattern
/// match on `isNetworkError` (retry later, no user-facing error needed)
/// vs. a real validation/business error the cashier should see.
class ApiException implements Exception {
  ApiException({
    required this.message,
    this.statusCode,
    this.isNetworkError = false,
    this.fieldErrors = const {},
  });

  factory ApiException.fromDio(DioException e) {
    final isNetwork = e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.connectionError;

    if (isNetwork) {
      return ApiException(message: 'No connection to the server.', isNetworkError: true);
    }

    final data = e.response?.data;
    final message = (data is Map && data['message'] is String)
        ? data['message'] as String
        : (e.message ?? 'Unexpected error.');

    return ApiException(
      message: message,
      statusCode: e.response?.statusCode,
      fieldErrors: _fieldErrorsFrom(data),
    );
  }

  /// Laravel's 422 body carries `errors: {field: [messages]}` alongside
  /// the summary `message`.
  ///
  /// Dropping it, as this class used to, meant an eight-field sign-up
  /// form could only ever show "The given data was invalid." — the server
  /// knew precisely which field was wrong and the app threw that away,
  /// leaving the vendor to guess. Flattened to one message per field
  /// because a text field has room for one.
  static Map<String, String> _fieldErrorsFrom(dynamic data) {
    if (data is! Map || data['errors'] is! Map) {
      return const {};
    }

    final errors = <String, String>{};

    (data['errors'] as Map).forEach((key, value) {
      if (key is! String) {
        return;
      }

      if (value is List && value.isNotEmpty) {
        errors[key] = value.first.toString();
      } else if (value is String) {
        errors[key] = value;
      }
    });

    return errors;
  }

  final String message;
  final int? statusCode;
  final bool isNetworkError;

  /// Keyed by the request field name, e.g. `owner_email`.
  final Map<String, String> fieldErrors;

  @override
  String toString() => message;
}
