import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:uuid/uuid.dart';

import '../local/database.dart';
import '../remote/sync_api.dart';

/// Push side of sync. `queueSale()` is the only method the POS screen
/// calls — it writes to the local outbox and returns immediately, whether
/// or not the server is reachable right now. SyncManager is solely
/// responsible for actually flushing the outbox later.
class SaleRepository {
  SaleRepository({required AppDatabase db, required SyncApi syncApi})
      : _db = db,
        _syncApi = syncApi;

  final AppDatabase _db;
  final SyncApi _syncApi;
  final _uuid = const Uuid();

  Stream<int> watchPendingCount() {
    final query = _db.selectOnly(_db.pendingSales)
      ..addColumns([_db.pendingSales.idempotencyKey.count()])
      ..where(_db.pendingSales.status.equals('pending'));

    return query.watchSingle().map((row) => row.read(_db.pendingSales.idempotencyKey.count()) ?? 0);
  }

  /// [sale] must already contain branch_id, warehouse_id, items, payments
  /// etc. — everything SyncController::pushSales() / SaleService::create()
  /// expect, except business_id/sold_by/source, which the server fills in
  /// from the authenticated token, never trusted from the client.
  ///
  /// The idempotency key is generated *here*, at the moment the cashier
  /// completes the sale — not later when it happens to sync — which is
  /// what makes a retried push after a dropped connection safe.
  Future<String> queueSale(Map<String, dynamic> sale) async {
    final key = _uuid.v4();
    final payload = {...sale, 'idempotency_key': key};

    await _db.into(_db.pendingSales).insert(
          PendingSalesCompanion.insert(
            idempotencyKey: key,
            payloadJson: jsonEncode(payload),
          ),
        );

    return key;
  }

  /// Sends every still-pending outbox row in one batch and applies the
  /// per-key results: `ok`/`rejected` are terminal (removed or marked, the
  /// cashier already saw the sale complete locally either way); `error` is
  /// left `pending` so the next sync pass retries it.
  Future<void> flushPending() async {
    final pending = await (_db.select(_db.pendingSales)
          ..where((t) => t.status.equals('pending')))
        .get();

    if (pending.isEmpty) {
      return;
    }

    final payloads = pending.map((row) => jsonDecode(row.payloadJson) as Map<String, dynamic>).toList();
    final results = await _syncApi.pushSales(payloads);

    await _db.transaction(() async {
      for (final row in pending) {
        final result = results[row.idempotencyKey] as Map<String, dynamic>?;

        if (result == null) {
          continue; // Server didn't report on this one — leave pending, retry next pass.
        }

        final status = result['status'] as String;

        if (status == 'ok') {
          await (_db.delete(_db.pendingSales)
                ..where((t) => t.idempotencyKey.equals(row.idempotencyKey)))
              .go();
        } else if (status == 'rejected') {
          await (_db.update(_db.pendingSales)
                ..where((t) => t.idempotencyKey.equals(row.idempotencyKey)))
              .write(PendingSalesCompanion(
            status: const Value('rejected'),
            lastError: Value(result['message'] as String?),
          ));
        } else {
          await (_db.update(_db.pendingSales)
                ..where((t) => t.idempotencyKey.equals(row.idempotencyKey)))
              .write(PendingSalesCompanion(
            lastError: Value(result['message'] as String?),
          ));
        }
      }
    });
  }
}
