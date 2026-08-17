import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/providers.dart';
import '../../data/remote/entitlement.dart';
import 'modules.dart';

/// The shell the desktop app opens into.
///
/// A rail of every vendor module, with POS wired up and the rest marked
/// as still on the way. The alternative — hiding what is not built — makes
/// the desktop app look like a different, smaller product than the one
/// the vendor signed up for, and leaves them guessing whether their data
/// is even there.
class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  int _selected = 0;

  Future<void> _signOut() async {
    await ref.read(authRepositoryProvider).logout();

    if (mounted) {
      context.go('/auth');
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final module = desktopModules[_selected];
    final entitlement = ref.read(authRepositoryProvider).entitlement;

    return Scaffold(
      body: Row(
        children: [
          NavigationRail(
            extended: MediaQuery.sizeOf(context).width > 1100,
            selectedIndex: _selected,
            onDestinationSelected: (index) => setState(() => _selected = index),
            leading: Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Icon(Icons.storefront, color: theme.colorScheme.primary),
            ),
            trailing: Expanded(
              child: Align(
                alignment: Alignment.bottomCenter,
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: IconButton(
                    tooltip: 'Sign out',
                    onPressed: _signOut,
                    icon: const Icon(Icons.logout),
                  ),
                ),
              ),
            ),
            destinations: [
              for (final m in desktopModules)
                // Every module is listed and selectable, including the
                // ones not built yet — selecting one explains where it
                // is, which beats a rail that silently omits most of the
                // product the vendor signed up for.
                NavigationRailDestination(icon: Icon(m.icon), label: Text(m.label)),
            ],
          ),
          const VerticalDivider(width: 1),
          Expanded(
            child: Column(
              children: [
                if (entitlement != null) _EntitlementBanner(entitlement: entitlement),
                Expanded(
                  child: module.available && module.route != null
                      ? _OpenModule(module: module)
                      : _ComingSoon(module: module),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// A live module is opened on its own route rather than embedded, so the
/// POS screen keeps the full window it was designed for.
class _OpenModule extends StatelessWidget {
  const _OpenModule({required this.module});

  final DesktopModule module;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 460),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(module.icon, size: 48, color: theme.colorScheme.primary),
            const SizedBox(height: 16),
            Text(module.label, style: theme.textTheme.headlineSmall),
            if (module.summary != null) ...[
              const SizedBox(height: 8),
              Text(
                module.summary!,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: theme.colorScheme.onSurfaceVariant),
              ),
            ],
            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: () => context.go(module.route!),
              icon: const Icon(Icons.arrow_forward),
              label: Text('Open ${module.label.toLowerCase()}'),
            ),
          ],
        ),
      ),
    );
  }
}

class _ComingSoon extends StatelessWidget {
  const _ComingSoon({required this.module});

  final DesktopModule module;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 460),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(module.icon, size: 48, color: theme.colorScheme.outline),
            const SizedBox(height: 16),
            Text(module.label, style: theme.textTheme.headlineSmall),
            if (module.summary != null) ...[
              const SizedBox(height: 8),
              Text(
                module.summary!,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: theme.colorScheme.onSurfaceVariant),
              ),
            ],
            const SizedBox(height: 20),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: theme.colorScheme.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                'Not in the desktop app yet — use the web dashboard for this.',
                style: theme.textTheme.bodySmall,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Shown only when there is something the vendor can act on. A permanent
/// banner reporting that everything is fine is a banner people stop
/// reading, including on the day it says something else.
class _EntitlementBanner extends StatelessWidget {
  const _EntitlementBanner({required this.entitlement});

  final Entitlement entitlement;

  @override
  Widget build(BuildContext context) {
    if (!entitlement.isUnknown && !entitlement.shouldWarnAboutExpiry) {
      return const SizedBox.shrink();
    }

    final theme = Theme.of(context);
    final offline = entitlement.isUnknown;

    return Material(
      color: offline
          ? theme.colorScheme.surfaceContainerHighest
          : theme.colorScheme.tertiaryContainer,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: [
            Icon(offline ? Icons.cloud_off : Icons.schedule, size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                offline
                    // Not "your subscription has expired". The app does
                    // not know that, and saying it to a shop with a bad
                    // connection would be a lie it cannot back up.
                    ? 'Working offline — sales are saved here and will sync when the connection returns.'
                    : 'Your trial ends in ${entitlement.daysRemaining} '
                        '${entitlement.daysRemaining == 1 ? 'day' : 'days'}.',
                style: theme.textTheme.bodySmall,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
