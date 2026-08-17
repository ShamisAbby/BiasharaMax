import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/local/database.dart';
import '../data/remote/location_api.dart';
import '../data/repositories/auth_repository.dart';
import '../data/repositories/product_repository.dart';
import '../data/repositories/sale_repository.dart';
import '../sync/sync_manager.dart';
import 'storage/secure_storage.dart';

/// Every provider here is a plain value overridden once at startup in
/// `main.dart`, not built lazily by Riverpod itself — the app's
/// dependencies (Dio, the Drift database, the repositories) need async
/// setup (reading the stored API base URL, opening the sqlite file)
/// before the widget tree exists at all, so they're constructed up front
/// and just handed to the tree via `ProviderScope(overrides: [...])`.
/// The `UnimplementedError` bodies below should never actually run.
final secureStorageProvider = Provider<SecureStorage>((ref) => throw UnimplementedError());

final appDatabaseProvider = Provider<AppDatabase>((ref) => throw UnimplementedError());

final authRepositoryProvider = Provider<AuthRepository>((ref) => throw UnimplementedError());

final productRepositoryProvider = Provider<ProductRepository>((ref) => throw UnimplementedError());

final saleRepositoryProvider = Provider<SaleRepository>((ref) => throw UnimplementedError());

final syncManagerProvider = Provider<SyncManager>((ref) => throw UnimplementedError());

final locationApiProvider = Provider<LocationApi>((ref) => throw UnimplementedError());
