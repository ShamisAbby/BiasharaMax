import '../../core/api/api_client.dart';
import '../../core/api/endpoints.dart';

/// Talks to SyncController — see
/// app/Http/Controllers/Api/SyncController.php. Raw JSON maps in and out
/// on purpose: this layer's only job is the HTTP call, decoding into
/// typed rows happens in the repositories that write to Drift.
class SyncApi {
  SyncApi(this._client);

  final ApiClient _client;

  /// [since] is the resource's saved watermark (see `SyncState` in the
  /// local database) — null on a resource's very first sync, which pulls
  /// the entire tenant catalog.
  /// [sinceId] is the second half of the keyset cursor. Sent while paging
  /// through a large first sync so a row sharing its `updated_at` with the
  /// previous page's last row isn't skipped; null on an ordinary
  /// incremental pull, where the timestamp alone is enough.
  Future<Map<String, dynamic>> pullProducts({DateTime? since, String? sinceId}) async {
    final response = await _client.get(
      Endpoints.syncProductsPull,
      query: since == null
          ? null
          : {
              'since': since.toUtc().toIso8601String(),
              if (sinceId != null) 'since_id': sinceId,
            },
    );

    return response.data as Map<String, dynamic>;
  }

  /// [sales] is a batch of queued outbox payloads, each already carrying
  /// its own client-generated `idempotency_key`. Returns a map keyed by
  /// that same idempotency_key -> {status, ...} so the caller can mark
  /// each outbox row individually instead of all-or-nothing.
  Future<Map<String, dynamic>> pushSales(List<Map<String, dynamic>> sales) async {
    final response = await _client.post(Endpoints.syncSalesPush, data: {'sales': sales});

    return (response.data as Map<String, dynamic>)['results'] as Map<String, dynamic>;
  }
}
