import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'core/providers.dart';
import 'data/repositories/auth_repository.dart';
import 'features/activation/activation_screen.dart';
import 'features/auth/auth_screen.dart';
import 'features/dashboard/dashboard_screen.dart';
import 'features/intro/intro_screen.dart';
import 'features/pos/location/location_screen.dart';
import 'features/pos/pos_home_screen.dart';

class BiasharaDesktopApp extends ConsumerStatefulWidget {
  const BiasharaDesktopApp({super.key});

  @override
  ConsumerState<BiasharaDesktopApp> createState() => _BiasharaDesktopAppState();
}

class _BiasharaDesktopAppState extends ConsumerState<BiasharaDesktopApp> {
  late final GoRouter _router;

  @override
  void initState() {
    super.initState();

    // Built once, in initState.
    //
    // It used to be constructed inside build(), which quietly threw the
    // router away and rebuilt it on every rebuild — losing navigation
    // history, and re-running the async redirect each time. The redirect
    // also called `await` on storage and the network, which GoRouter runs
    // on *every* navigation; a POS app that consults the server before
    // each screen change is one that stalls whenever the connection does.
    // The decision is now made once at launch by _Splash.
    _router = GoRouter(
      initialLocation: '/',
      routes: [
        GoRoute(path: '/', builder: (context, state) => const _Splash()),
        GoRoute(path: '/intro', builder: (context, state) => const IntroScreen()),
        GoRoute(path: '/auth', builder: (context, state) => const AuthScreen()),
        GoRoute(path: '/activation', builder: (context, state) => const ActivationScreen()),
        GoRoute(path: '/dashboard', builder: (context, state) => const DashboardScreen()),
        GoRoute(path: '/select-location', builder: (context, state) => const LocationScreen()),
        GoRoute(path: '/pos', builder: (context, state) => const _PosGate()),
      ],
    );
  }

  @override
  void dispose() {
    _router.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'BiasharaMax Desktop',
      theme: ThemeData(colorSchemeSeed: const Color(0xFF0F766E), useMaterial3: true),
      routerConfig: _router,
    );
  }
}

/// Decides where the app opens, once.
class _Splash extends ConsumerStatefulWidget {
  const _Splash();

  @override
  ConsumerState<_Splash> createState() => _SplashState();
}

class _SplashState extends ConsumerState<_Splash> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _decide());
  }

  Future<void> _decide() async {
    final destination = await ref.read(authRepositoryProvider).decideStartup();

    if (!mounted) {
      return;
    }

    switch (destination) {
      case StartupDestination.intro:
        context.go('/intro');
      case StartupDestination.auth:
        context.go('/auth');
      case StartupDestination.activation:
        context.go('/activation');
      case StartupDestination.dashboard:
        ref.read(syncManagerProvider).start();
        context.go('/dashboard');
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}

/// The POS needs a warehouse and a branch before it can take money.
///
/// Checked on the way in rather than in a global redirect, because it is
/// a requirement of this one screen — a vendor looking at Inventory has
/// no reason to be asked which till they are standing at. Both are
/// needed, not just the warehouse: a sale carries `branch_id` too, and
/// sending a cashier to the POS without one only defers the failure to
/// the moment they try to charge someone.
class _PosGate extends ConsumerStatefulWidget {
  const _PosGate();

  @override
  ConsumerState<_PosGate> createState() => _PosGateState();
}

class _PosGateState extends ConsumerState<_PosGate> {
  /// Resolved once in initState, not in build.
  ///
  /// A `FutureBuilder` handed a future created inside `build()` starts a
  /// new one on every rebuild and never settles — the screen would sit on
  /// a spinner, reading secure storage in a loop.
  late final Future<bool> _hasLocation;

  @override
  void initState() {
    super.initState();

    _hasLocation = _resolveLocation();
  }

  Future<bool> _resolveLocation() async {
    final storage = ref.read(secureStorageProvider);

    return await storage.getActiveWarehouseId() != null &&
        await storage.getActiveBranchId() != null;
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<bool>(
      future: _hasLocation,
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const Scaffold(body: Center(child: CircularProgressIndicator()));
        }

        return snapshot.data! ? const PosHomeScreen() : const LocationScreen();
      },
    );
  }
}
