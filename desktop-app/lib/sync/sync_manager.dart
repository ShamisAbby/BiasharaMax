import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';

import '../core/api/api_client.dart';
import '../data/repositories/product_repository.dart';
import '../data/repositories/sale_repository.dart';

enum SyncStatus { idle, syncing, offline, error }

/// The single place that decides "is it time to talk to the server".
/// Runs on three triggers: app start, connectivity regained, and a
/// periodic timer as a safety net (connectivity_plus doesn't catch every
/// "technically connected but the API is unreachable" case).
///
/// Order matters: sales are pushed *before* products are pulled, so a
/// sale that would drop stock below zero server-side is evaluated against
/// the state that existed when it was rung up, not against whatever
/// changed elsewhere in the meantime.
class SyncManager {
  SyncManager({
    required ProductRepository products,
    required SaleRepository sales,
    Duration interval = const Duration(minutes: 5),
  })  : _products = products,
        _sales = sales,
        _interval = interval;

  final ProductRepository _products;
  final SaleRepository _sales;
  final Duration _interval;

  Timer? _timer;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;
  final _statusController = StreamController<SyncStatus>.broadcast();

  Stream<SyncStatus> get status => _statusController.stream;

  void start() {
    _timer = Timer.periodic(_interval, (_) => syncNow());

    _connectivitySub = Connectivity().onConnectivityChanged.listen((results) {
      if (!results.contains(ConnectivityResult.none)) {
        syncNow();
      }
    });

    syncNow();
  }

  void dispose() {
    _timer?.cancel();
    _connectivitySub?.cancel();
    _statusController.close();
  }

  Future<void> syncNow() async {
    _statusController.add(SyncStatus.syncing);

    try {
      // Outbox first (see class doc), then pull the catalog.
      await _sales.flushPending();
      await _products.pull();

      _statusController.add(SyncStatus.idle);
    } on ApiException catch (e) {
      _statusController.add(e.isNetworkError ? SyncStatus.offline : SyncStatus.error);
    } catch (_) {
      _statusController.add(SyncStatus.error);
    }
  }
}
