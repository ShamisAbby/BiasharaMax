import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/providers.dart';
import '../../data/local/database.dart';
import '../../sync/sync_manager.dart';
import 'cart/cart_controller.dart';
import 'cart/cart_panel.dart';

final _searchQueryProvider = StateProvider.autoDispose<String>((ref) => '');

/// Two-pane POS layout: searchable product catalog on the left, the live
/// cart + checkout entry point on the right (CartPanel). This is the
/// screen a cashier actually spends their shift on.
class PosHomeScreen extends ConsumerWidget {
  const PosHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final syncManager = ref.watch(syncManagerProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('BiasharaMax Desktop'),
        actions: [
          StreamBuilder<SyncStatus>(
            stream: syncManager.status,
            builder: (context, snapshot) {
              final status = snapshot.data ?? SyncStatus.idle;

              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Center(child: Text(_statusLabel(status))),
              );
            },
          ),
          IconButton(icon: const Icon(Icons.sync), tooltip: 'Sync now', onPressed: syncManager.syncNow),
          IconButton(
            icon: const Icon(Icons.store),
            tooltip: 'Change warehouse',
            onPressed: () => context.go('/select-location'),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Sign out',
            onPressed: () async {
              await ref.read(authRepositoryProvider).logout();
              if (context.mounted) context.go('/login');
            },
          ),
        ],
      ),
      // Const all the way down. Nothing in this subtree depends on the
      // screen's own state — each child watches the providers it needs —
      // so the whole thing is built once and skipped on every rebuild the
      // AppBar's sync indicator causes, which is one per sync tick.
      body: const Column(
        children: [
          _PendingOutboxBanner(),
          Expanded(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(flex: 2, child: _ProductCatalog()),
                VerticalDivider(width: 1),
                Expanded(child: CartPanel()),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _statusLabel(SyncStatus status) => switch (status) {
        SyncStatus.idle => 'Synced',
        SyncStatus.syncing => 'Syncing…',
        SyncStatus.offline => 'Offline',
        SyncStatus.error => 'Sync error',
      };
}

class _ProductCatalog extends ConsumerWidget {
  const _ProductCatalog();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final query = ref.watch(_searchQueryProvider);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: TextField(
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'Search by name, SKU, or barcode',
              border: OutlineInputBorder(),
            ),
            onChanged: (value) => ref.read(_searchQueryProvider.notifier).state = value,
          ),
        ),
        Expanded(
          child: StreamBuilder<List<Product>>(
            stream: ref.read(productRepositoryProvider).watchAll(),
            builder: (context, snapshot) {
              final all = snapshot.data ?? [];
              final filtered = query.trim().isEmpty
                  ? all
                  : all.where((p) {
                      final q = query.toLowerCase();

                      return p.name.toLowerCase().contains(q) ||
                          (p.sku?.toLowerCase().contains(q) ?? false) ||
                          (p.barcode?.toLowerCase().contains(q) ?? false);
                    }).toList();

              if (all.isEmpty) {
                return const Center(child: Text('No products synced yet.'));
              }

              if (filtered.isEmpty) {
                return const Center(child: Text('No matches.'));
              }

              return GridView.builder(
                padding: const EdgeInsets.all(12),
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                  maxCrossAxisExtent: 220,
                  childAspectRatio: 1.3,
                  crossAxisSpacing: 8,
                  mainAxisSpacing: 8,
                ),
                itemCount: filtered.length,
                itemBuilder: (context, index) {
                  final product = filtered[index];

                  return Card(
                    child: InkWell(
                      onTap: () => ref.read(cartControllerProvider.notifier).addProduct(product),
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              product.name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.titleSmall,
                            ),
                            Text(product.sku ?? '', style: Theme.of(context).textTheme.bodySmall),
                            Text('TZS ${product.sellingPrice.toStringAsFixed(0)}'),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              );
            },
          ),
        ),
      ],
    );
  }
}

class _PendingOutboxBanner extends ConsumerWidget {
  const _PendingOutboxBanner();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return StreamBuilder<int>(
      stream: ref.read(saleRepositoryProvider).watchPendingCount(),
      builder: (context, snapshot) {
        final pending = snapshot.data ?? 0;

        if (pending == 0) {
          return const SizedBox.shrink();
        }

        return Container(
          width: double.infinity,
          color: Theme.of(context).colorScheme.secondaryContainer,
          padding: const EdgeInsets.all(8),
          child: Text('$pending sale(s) waiting to sync', textAlign: TextAlign.center),
        );
      },
    );
  }
}
