import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/providers.dart';
import '../../data/remote/registration_api.dart';
import '../auth/signup_draft.dart';

/// "Enter a product key, or start a free trial."
///
/// One screen serving two arrivals, which is why it reads its state
/// rather than assuming it:
///
///  * straight from sign-up — a [SignupDraft] is waiting, and either
///    button submits the whole registration in a single request;
///  * from signing in to an account the server would not admit — there is
///    no draft, so the trial is not on offer (a trial you can restart from
///    the sign-in screen is a free product with extra steps) and the key
///    activates this machine instead.
class ActivationScreen extends ConsumerStatefulWidget {
  const ActivationScreen({super.key});

  @override
  ConsumerState<ActivationScreen> createState() => _ActivationScreenState();
}

class _ActivationScreenState extends ConsumerState<ActivationScreen> {
  final _productKeyController = TextEditingController();

  bool _submitting = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};
  Future<RegistrationOptions>? _optionsFuture;

  @override
  void initState() {
    super.initState();

    // Only asked for when a trial might actually be offered — there is no
    // reason to hit the network to label a button nobody will see.
    if (ref.read(signupDraftProvider) != null) {
      _optionsFuture = ref.read(authRepositoryProvider).registrationOptions();
    }
  }

  @override
  void dispose() {
    _productKeyController.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _submitting = true;
      _error = null;
      _fieldErrors = const {};
    });

    try {
      await action();
    } on ApiException catch (e) {
      // Guarded: the action can navigate away, and a setState after that
      // throws rather than showing anyone the error.
      if (mounted) {
        setState(() {
          _error = e.message;
          _fieldErrors = e.fieldErrors;
        });
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  /// Sign-up path. Creates the business on the chosen footing.
  Future<void> _registerWith({String? productKey}) async {
    final draft = ref.read(signupDraftProvider)!;

    await _run(() async {
      final entitlement = await ref.read(authRepositoryProvider).register(
            ownerName: draft.ownerName,
            ownerEmail: draft.ownerEmail,
            password: draft.password,
            passwordConfirmation: draft.passwordConfirmation,
            businessName: draft.businessName,
            businessType: draft.businessType,
            country: draft.country,
            currency: draft.currency,
            ownerPhone: draft.ownerPhone,
            registrationCode: productKey,
          );

      if (!mounted) {
        return;
      }

      // Cleared on success only. Holding a password in memory after it
      // has been used for anything is avoidable; clearing it on failure
      // would send the vendor back to retype the entire form.
      ref.read(signupDraftProvider.notifier).state = null;

      if (entitlement.allowed) {
        ref.read(syncManagerProvider).start();
        context.go('/dashboard');
      } else {
        setState(() => _error = entitlement.message);
      }
    });
  }

  /// Sign-in path. Activates this machine against an existing licence.
  Future<void> _activateDevice() async {
    await _run(() async {
      final entitlement = await ref
          .read(authRepositoryProvider)
          .activate(_productKeyController.text.trim());

      if (!mounted) {
        return;
      }

      if (entitlement.allowed) {
        ref.read(syncManagerProvider).start();
        context.go('/dashboard');
      } else {
        // Activated the device but still not admitted — almost always a
        // lapsed subscription behind a valid key. Saying so beats
        // "activation failed", which sends someone hunting for a new key
        // that will not help.
        setState(() => _error = entitlement.message);
      }
    });
  }

  Future<void> _submitKey() {
    if (_productKeyController.text.trim().isEmpty) {
      setState(() => _fieldErrors = {'registration_code': 'Enter your product key.'});

      return Future.value();
    }

    return ref.read(signupDraftProvider) != null
        ? _registerWith(productKey: _productKeyController.text.trim())
        : _activateDevice();
  }

  Future<void> _signOut() async {
    await ref.read(authRepositoryProvider).logout();

    if (mounted) {
      context.go('/auth');
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final draft = ref.watch(signupDraftProvider);
    final isSignUp = draft != null;
    final entitlement = ref.read(authRepositoryProvider).entitlement;

    return Scaffold(
      body: Center(
        child: SingleChildScrollView(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Activate BiasharaMax', style: theme.textTheme.headlineSmall),
                  const SizedBox(height: 8),
                  Text(
                    isSignUp
                        ? 'One last step for ${draft.businessName}.'
                        : entitlement?.message ??
                            'This account needs an active subscription to continue.',
                    style: theme.textTheme.bodyMedium
                        ?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                  ),
                  const SizedBox(height: 28),
                  TextField(
                    controller: _productKeyController,
                    textCapitalization: TextCapitalization.characters,
                    decoration: InputDecoration(
                      labelText: 'Product key',
                      hintText: 'BMAX-XXXX-XXXX-XXXX-XXXX',
                      border: const OutlineInputBorder(),
                      errorText: _fieldErrors['registration_code'] ?? _fieldErrors['license_key'],
                    ),
                    onSubmitted: (_) => _submitting ? null : _submitKey(),
                  ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: _submitting ? null : _submitKey,
                    child: _submitting
                        ? const SizedBox(
                            height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Text('Use product key'),
                  ),
                  if (isSignUp) ...[
                    const SizedBox(height: 24),
                    Row(
                      children: [
                        const Expanded(child: Divider()),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          child: Text('or', style: theme.textTheme.bodySmall),
                        ),
                        const Expanded(child: Divider()),
                      ],
                    ),
                    const SizedBox(height: 24),
                    _trialButton(theme),
                  ],
                  if (_error != null) ...[
                    const SizedBox(height: 20),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: theme.colorScheme.errorContainer,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.error_outline,
                              size: 18, color: theme.colorScheme.onErrorContainer),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _error!,
                              style: TextStyle(color: theme.colorScheme.onErrorContainer),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  TextButton(
                    onPressed: _submitting
                        ? null
                        : isSignUp
                            ? () => context.go('/auth')
                            : _signOut,
                    child: Text(isSignUp ? 'Back' : 'Sign in as someone else'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _trialButton(ThemeData theme) {
    return FutureBuilder<RegistrationOptions>(
      future: _optionsFuture,
      builder: (context, snapshot) {
        final days = snapshot.data?.trialDays;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            OutlinedButton.icon(
              // Disabled until the length is known, rather than falling
              // back to "30". An operator who shortened the trial on the
              // platform should not discover that the desktop app has
              // been promising 30 days regardless.
              onPressed: _submitting || days == null ? null : () => _registerWith(),
              icon: const Icon(Icons.schedule),
              label: Text(days == null ? 'Checking trial…' : 'Start $days-day free trial'),
            ),
            if (snapshot.hasError)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'Could not reach the server to check the trial. You can still use a product key.',
                  style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.error),
                ),
              )
            else ...[
              const SizedBox(height: 8),
              Text(
                'No card needed. You can enter a product key later.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodySmall
                    ?.copyWith(color: theme.colorScheme.onSurfaceVariant),
              ),
            ],
          ],
        );
      },
    );
  }
}
