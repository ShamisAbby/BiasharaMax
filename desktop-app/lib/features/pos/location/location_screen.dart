import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_client.dart';
import '../../../core/providers.dart';
import '../../../data/remote/location_api.dart';

/// Points this till at a branch and a warehouse.
///
/// Both, not just the warehouse. A sale needs `branch_id` as well, and the
/// app used to take that solely from the signed-in employee's own record —
/// which is optional when inviting staff. Anyone invited without a branch
/// could fill a cart, hit checkout, and be told to "sign out and select
/// one again" by a screen that never offered the choice. Asking here ends
/// that dead end.
///
/// Answered once and remembered; this screen only reappears if the till is
/// deliberately reset or a cashier taps "change warehouse".
class LocationScreen extends ConsumerStatefulWidget {
  const LocationScreen({super.key});

  @override
  ConsumerState<LocationScreen> createState() => _LocationScreenState();
}

class _LocationScreenState extends ConsumerState<LocationScreen> {
  late Future<LocationOptions> _options;
  BranchOption? _branch;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _options = ref.read(locationApiProvider).fetch();
  }

  Future<void> _select(BranchOption branch, WarehouseOption warehouse) async {
    final storage = ref.read(secureStorageProvider);

    await storage.setActiveBranchId(branch.id);
    await storage.setActiveWarehouseId(warehouse.id);

    if (mounted) context.go('/pos');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Where does this till sell from?')),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: FutureBuilder<LocationOptions>(
              future: _options,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (snapshot.hasError) {
                  final error = snapshot.error;

                  return _Message(
                    // A till is set up once, usually by someone standing
                    // next to it — so "try again" is a real option here,
                    // unlike mid-shift where the app must stay offline-first.
                    text: error is ApiException && error.isNetworkError
                        ? 'Can\'t reach the server. This one-off setup step needs a connection.'
                        : 'Could not load branches: $error',
                    onRetry: () => setState(_load),
                  );
                }

                final options = snapshot.data!;
                final branches = options.branches;

                if (branches.isEmpty) {
                  return const _Message(
                    text: 'This business has no active branches yet. '
                        'Create one in the web dashboard first.',
                  );
                }

                final branch = _branch ??
                    branches.firstWhere(
                      (b) => b.id == options.defaultBranchId,
                      orElse: () => branches.first,
                    );

                return Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('Branch', style: Theme.of(context).textTheme.labelLarge),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      initialValue: branch.id,
                      decoration: const InputDecoration(border: OutlineInputBorder()),
                      items: [
                        for (final b in branches)
                          DropdownMenuItem(
                            value: b.id,
                            child: Text(b.city == null ? b.name : '${b.name} — ${b.city}'),
                          ),
                      ],
                      onChanged: (id) => setState(
                        () => _branch = branches.firstWhere((b) => b.id == id),
                      ),
                    ),
                    const SizedBox(height: 24),
                    Text('Warehouse', style: Theme.of(context).textTheme.labelLarge),
                    const SizedBox(height: 8),
                    if (branch.warehouses.isEmpty)
                      const _Message(
                        text: 'This branch has no active warehouse. '
                            'Add one in the web dashboard, or choose another branch.',
                      )
                    else
                      // Listed rather than a second dropdown: most branches
                      // have one or two warehouses, and one tap beats two.
                      for (final warehouse in branch.warehouses)
                        Card(
                          child: ListTile(
                            title: Text(warehouse.name),
                            subtitle: warehouse.isDefault ? const Text('Default') : null,
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () => _select(branch, warehouse),
                          ),
                        ),
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.text, this.onRetry});

  final String text;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(text, textAlign: TextAlign.center),
        if (onRetry != null) ...[
          const SizedBox(height: 16),
          FilledButton(onPressed: onRetry, child: const Text('Try again')),
        ],
      ],
    );
  }
}
