import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

part 'database.g.dart';

/// Local cache of the tenant's product catalog + per-warehouse stock,
/// mirrored from `GET /v1/sync/products` (SyncController::pullProducts).
/// Read-only from the app's point of view — products are edited on the
/// web app / a future Flutter screen that writes back through its own
/// sync path, not by mutating this table directly and hoping it syncs.
@DataClassName('Product')
class Products extends Table {
  TextColumn get id => text()();
  TextColumn get categoryId => text().nullable()();
  TextColumn get brandId => text().nullable()();
  TextColumn get unitId => text().nullable()();
  TextColumn get name => text()();
  TextColumn get sku => text().nullable()();
  TextColumn get barcode => text().nullable()();
  TextColumn get productType => text()();
  BoolColumn get trackStock => boolean().withDefault(const Constant(true))();
  RealColumn get costPrice => real().withDefault(const Constant(0))();
  RealColumn get sellingPrice => real().withDefault(const Constant(0))();
  RealColumn get wholesalePrice => real().nullable()();
  RealColumn get taxRate => real().withDefault(const Constant(0))();
  TextColumn get status => text()();
  DateTimeColumn get updatedAt => dateTime()();
  DateTimeColumn get deletedAt => dateTime().nullable()();

  @override
  Set<Column> get primaryKey => {id};
}

/// One row per product per warehouse — mirrors the `inventories` table's
/// shape server-side (see app/Modules/Inventory/Models/Inventory.php).
@DataClassName('Inventory')
class Inventories extends Table {
  TextColumn get id => text()();
  TextColumn get productId => text()();
  TextColumn get warehouseId => text()();
  RealColumn get quantity => real().withDefault(const Constant(0))();
  RealColumn get averageCost => real().withDefault(const Constant(0))();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}

/// The offline outbox: every sale completed while this device had no (or
/// unreliable) connection to the server sits here until SyncManager
/// successfully pushes it. `idempotencyKey` is generated client-side at
/// the moment the cashier completes the sale — not when it happens to
/// sync — so a retried push after a dropped connection can't ever
/// double-create the sale server-side (see SaleService::create()'s
/// idempotency check).
@DataClassName('PendingSale')
class PendingSales extends Table {
  TextColumn get idempotencyKey => text()();

  /// The full sale payload (branch_id, warehouse_id, items, payments,
  /// ...) as JSON, shaped exactly like what SyncController::pushSales()
  /// expects — encoding it once here means SyncManager doesn't need to
  /// know anything about sale structure, just "send this blob".
  TextColumn get payloadJson => text()();

  /// pending -> synced | rejected | error. `rejected` (a real business
  /// rule failure, e.g. credit limit exceeded) is terminal and shown to
  /// the cashier; `error` (a transient server/network problem) is
  /// retried by SyncManager on the next pass.
  TextColumn get status => text().withDefault(const Constant('pending'))();
  TextColumn get lastError => text().nullable()();
  DateTimeColumn get createdAt => dateTime().withDefault(currentDateAndTime)();
  DateTimeColumn get syncedAt => dateTime().nullable()();

  @override
  Set<Column> get primaryKey => {idempotencyKey};
}

/// Per-resource sync watermark ("products last synced as of X") so a pull
/// only ever asks for what changed since last time, not the whole catalog
/// on every app start.
@DataClassName('SyncState')
class SyncStates extends Table {
  TextColumn get resource => text()();
  DateTimeColumn get lastSyncedAt => dateTime().nullable()();

  @override
  Set<Column> get primaryKey => {resource};
}

@DriftDatabase(tables: [Products, Inventories, PendingSales, SyncStates])
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_openConnection());

  @override
  int get schemaVersion => 1;
}

LazyDatabase _openConnection() {
  return LazyDatabase(() async {
    final dir = await getApplicationSupportDirectory();
    final file = File(p.join(dir.path, 'biasharamax_desktop.sqlite'));

    return NativeDatabase.createInBackground(file);
  });
}
