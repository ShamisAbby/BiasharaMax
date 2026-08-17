import 'package:drift/drift.dart';

import '../local/database.dart';
import '../remote/sync_api.dart';

/// Pull side of sync for the product catalog. Screens read products via
/// `watchAll()` (a live Drift stream) and never call the network
/// directly — SyncManager is the only thing that calls `pull()`.
class ProductRepository {
  ProductRepository({required AppDatabase db, required SyncApi syncApi})
      : _db = db,
        _syncApi = syncApi;

  final AppDatabase _db;
  final SyncApi _syncApi;

  static const _resource = 'products';

  /// Sellable products only. `status` is synced but was never filtered,
  /// so discontinued and draft products showed up in the till alongside
  /// live ones — a cashier could ring up something the business had
  /// deliberately taken off sale.
  Stream<List<Product>> watchAll() =>
      (_db.select(_db.products)..where((t) => t.status.equals('active'))).watch();

  Stream<List<Inventory>> watchInventoryFor(String productId) =>
      (_db.select(_db.inventories)..where((t) => t.productId.equals(productId))).watch();

  /// Every warehouse_id seen across synced inventory rows — there's no
  /// dedicated `/v1/warehouses` endpoint yet (see README "Known gaps"), so
  /// this is how LocationScreen offers a choice without one. Good enough
  /// to pick a warehouse by id; showing a human name is a follow-up once
  /// the backend exposes one.
  Future<List<String>> distinctWarehouseIds() async {
    final query = _db.selectOnly(_db.inventories, distinct: true)
      ..addColumns([_db.inventories.warehouseId]);

    final rows = await query.get();

    return rows.map((row) => row.read(_db.inventories.warehouseId)!).toList();
  }

  /// Pulls everything changed since this resource's saved watermark,
  /// upserts it locally, and advances the watermark to the server's own
  /// clock (not the client's) so a slow client clock can never cause
  /// rows to be skipped on the next pull.
  ///
  /// Paging is a loop, not recursion, and the watermark is only advanced
  /// to `server_time` on the final page. The previous version advanced it
  /// *before* fetching the next page, then called itself — so the second
  /// call asked the server for rows changed since "now" and got nothing.
  /// A catalog over one page therefore synced its first 500 products and
  /// silently stopped, permanently: the watermark had already moved past
  /// the rows it never fetched, so no later sync would pick them up
  /// either.
  Future<void> pull() async {
    final state = await (_db.select(_db.syncStates)..where((t) => t.resource.equals(_resource)))
        .getSingleOrNull();

    DateTime? since = state?.lastSyncedAt;
    String? sinceId;

    while (true) {
      final done = await _pullPage(since: since, sinceId: sinceId);

      if (done.isFinalPage) {
        return;
      }

      since = done.nextSince;
      sinceId = done.nextSinceId;
    }
  }

  Future<_PullPage> _pullPage({DateTime? since, String? sinceId}) async {
    final response = await _syncApi.pullProducts(since: since, sinceId: sinceId);
    final rows = (response['data'] as List).cast<Map<String, dynamic>>();

    await _db.transaction(() async {
      for (final row in rows) {
        if (row['deleted_at'] != null) {
          await (_db.delete(_db.products)..where((t) => t.id.equals(row['id'] as String))).go();

          continue;
        }

        await _db.into(_db.products).insertOnConflictUpdate(
              ProductsCompanion.insert(
                id: row['id'] as String,
                categoryId: Value(row['category_id'] as String?),
                brandId: Value(row['brand_id'] as String?),
                unitId: Value(row['unit_id'] as String?),
                name: row['name'] as String,
                sku: Value(row['sku'] as String?),
                barcode: Value(row['barcode'] as String?),
                productType: row['product_type'] as String,
                trackStock: Value(row['track_stock'] == true),
                costPrice: Value((row['cost_price'] as num?)?.toDouble() ?? 0),
                sellingPrice: Value((row['selling_price'] as num?)?.toDouble() ?? 0),
                wholesalePrice: Value((row['wholesale_price'] as num?)?.toDouble()),
                taxRate: Value((row['tax_rate'] as num?)?.toDouble() ?? 0),
                status: row['status'] as String,
                updatedAt: DateTime.parse(row['updated_at'] as String),
              ),
            );

        for (final inv in (row['inventories'] as List? ?? [])) {
          final invRow = inv as Map<String, dynamic>;

          await _db.into(_db.inventories).insertOnConflictUpdate(
                InventoriesCompanion.insert(
                  id: invRow['id'] as String,
                  productId: row['id'] as String,
                  warehouseId: invRow['warehouse_id'] as String,
                  quantity: Value((invRow['quantity'] as num?)?.toDouble() ?? 0),
                  averageCost: Value((invRow['average_cost'] as num?)?.toDouble() ?? 0),
                  updatedAt: DateTime.parse(invRow['updated_at'] as String),
                ),
              );
        }
      }

      // Only once there is nothing left to page through. Advancing the
      // watermark mid-run would skip whatever hasn't been fetched yet.
      if (response['has_more'] != true) {
        final serverTime = DateTime.parse(response['server_time'] as String);

        await _db.into(_db.syncStates).insertOnConflictUpdate(
              SyncStatesCompanion.insert(resource: _resource, lastSyncedAt: Value(serverTime)),
            );
      }
    });

    if (response['has_more'] != true) {
      return const _PullPage.finalPage();
    }

    // Keyset cursor: (updated_at, id). The id tiebreaker is what stops a
    // row sharing its timestamp with the last row of a page from being
    // skipped — which a bulk import makes near-certain, since it writes
    // hundreds of rows within the same second.
    return _PullPage(
      nextSince: DateTime.parse(response['next_since'] as String),
      nextSinceId: response['next_since_id'] as String?,
    );
  }
}

/// Where the next page starts, or that there isn't one.
class _PullPage {
  const _PullPage({required this.nextSince, required this.nextSinceId})
      : isFinalPage = false;

  const _PullPage.finalPage()
      : isFinalPage = true,
        nextSince = null,
        nextSinceId = null;

  final bool isFinalPage;
  final DateTime? nextSince;
  final String? nextSinceId;
}
