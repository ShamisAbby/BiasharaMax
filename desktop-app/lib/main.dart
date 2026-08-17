import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/api/api_client.dart';
import 'core/config/app_config.dart';
import 'core/hardware/device_fingerprint.dart';
import 'core/providers.dart';
import 'core/storage/secure_storage.dart';
import 'data/local/database.dart';
import 'data/remote/auth_api.dart';
import 'data/remote/license_api.dart';
import 'data/remote/location_api.dart';
import 'data/remote/registration_api.dart';
import 'data/remote/sync_api.dart';
import 'data/repositories/auth_repository.dart';
import 'data/repositories/product_repository.dart';
import 'data/repositories/sale_repository.dart';
import 'sync/sync_manager.dart';

/// Wires the whole dependency graph once, up front — see the comment on
/// `core/providers.dart` for why this isn't built lazily through Riverpod
/// itself. Nothing here is business logic; it's assembly only.
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Not `SecureStorage(const FlutterSecureStorage())` — the default macOS
  // options select a Keychain this app cannot use. See SecureStorage.
  final storage = SecureStorage.withDefaults();
  final apiBaseUrl = await storage.getApiBaseUrl() ?? AppConfig.fallback.apiBaseUrl;
  final config = AppConfig(apiBaseUrl: apiBaseUrl);

  final apiClient = ApiClient(config: config, storage: storage);
  final db = AppDatabase();
  final fingerprint = DeviceFingerprint(storage);

  final authRepository = AuthRepository(
    authApi: AuthApi(apiClient),
    licenseApi: LicenseApi(apiClient),
    registrationApi: RegistrationApi(apiClient),
    storage: storage,
    fingerprint: fingerprint,
  );

  final syncApi = SyncApi(apiClient);
  final locationApi = LocationApi(apiClient);
  final productRepository = ProductRepository(db: db, syncApi: syncApi);
  final saleRepository = SaleRepository(db: db, syncApi: syncApi);
  final syncManager = SyncManager(products: productRepository, sales: saleRepository);

  // Sync is no longer started here. It used to begin the moment a token
  // existed, which meant a business whose trial had lapsed spent its
  // first minutes after launch pushing sales the server would refuse —
  // and the failures surfaced as sync errors rather than as the
  // subscription problem they actually were. The startup check now
  // decides, and starts sync only once entitlement is confirmed (see
  // _Splash in app.dart).

  runApp(
    ProviderScope(
      overrides: [
        secureStorageProvider.overrideWithValue(storage),
        appDatabaseProvider.overrideWithValue(db),
        authRepositoryProvider.overrideWithValue(authRepository),
        productRepositoryProvider.overrideWithValue(productRepository),
        saleRepositoryProvider.overrideWithValue(saleRepository),
        syncManagerProvider.overrideWithValue(syncManager),
        locationApiProvider.overrideWithValue(locationApi),
      ],
      child: const BiasharaDesktopApp(),
    ),
  );
}
