import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/providers.dart';
import '../../data/remote/registration_api.dart';
import 'signup_draft.dart';

/// Sign in and sign up as two panels of one screen.
///
/// Signing up does not create anything here. The details are collected,
/// held in a [SignupDraft], and carried to the activation step — where
/// choosing a product key or a free trial submits the whole thing in one
/// request. Creating the business first and applying the subscription
/// after would leave a half-provisioned business behind every time the
/// second step failed, and those are invisible until someone opens the
/// accounting screen months later.
class AuthScreen extends ConsumerStatefulWidget {
  const AuthScreen({super.key});

  @override
  ConsumerState<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends ConsumerState<AuthScreen> {
  static const _slide = Duration(milliseconds: 320);

  bool _signUp = false;
  bool _submitting = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};

  // Sign in
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  // Sign up
  final _ownerNameController = TextEditingController();
  final _ownerEmailController = TextEditingController();
  final _businessNameController = TextEditingController();
  final _signupPasswordController = TextEditingController();
  final _signupPasswordConfirmController = TextEditingController();

  String? _businessType;

  // No defaults.
  //
  // These were hardcoded to TZ/TZS, which the analyzer noticed only
  // because nothing ever reassigned them — the real problem being that a
  // Kenyan shop signing up got Tanzanian shillings stamped on every
  // price, sale and ledger entry it would ever record, with no field on
  // the form to say otherwise. Currency in particular cannot be corrected
  // afterwards without the books disagreeing with themselves.
  String? _country;
  String? _currency;

  Future<RegistrationOptions>? _optionsFuture;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _ownerNameController.dispose();
    _ownerEmailController.dispose();
    _businessNameController.dispose();
    _signupPasswordController.dispose();
    _signupPasswordConfirmController.dispose();
    super.dispose();
  }

  void _switchTo({required bool signUp}) {
    setState(() {
      _signUp = signUp;
      // Errors belong to the form that produced them. Leaving "wrong
      // password" on screen while someone fills in a sign-up form reads
      // as a complaint about what they are currently typing.
      _error = null;
      _fieldErrors = const {};

      // Fetched once, on first arrival at the sign-up form. Assigning it
      // in build() would start a fresh request on every keystroke.
      if (signUp) {
        _optionsFuture ??= ref.read(authRepositoryProvider).registrationOptions();
      }
    });
  }

  Future<void> _signIn() async {
    setState(() {
      _submitting = true;
      _error = null;
      _fieldErrors = const {};
    });

    try {
      await ref.read(authRepositoryProvider).login(
            email: _emailController.text.trim(),
            password: _passwordController.text,
          );

      final entitlement = ref.read(authRepositoryProvider).entitlement;

      if (!mounted) {
        return;
      }

      // Sync only once the account is actually admitted. Starting it for
      // a locked business means a stream of 403s in the background while
      // the vendor reads a message about renewing.
      if (entitlement?.allowed ?? false) {
        ref.read(syncManagerProvider).start();
        context.go('/dashboard');
      } else {
        context.go('/activation');
      }
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _fieldErrors = e.fieldErrors;
      });
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _continueSignUp() {
    final missing = _validateSignUp();

    if (missing.isNotEmpty) {
      setState(() {
        _fieldErrors = missing;
        // Checked here rather than posted and bounced back: these are
        // things the form already knows are wrong, and a round trip to be
        // told so is a round trip that can also fail.
        _error = 'Check the highlighted fields.';
      });

      return;
    }

    ref.read(signupDraftProvider.notifier).state = SignupDraft(
      ownerName: _ownerNameController.text.trim(),
      ownerEmail: _ownerEmailController.text.trim(),
      businessName: _businessNameController.text.trim(),
      businessType: _businessType!,
      country: _country!,
      currency: _currency!,
      password: _signupPasswordController.text,
      passwordConfirmation: _signupPasswordConfirmController.text,
    );

    context.go('/activation');
  }

  Map<String, String> _validateSignUp() {
    final errors = <String, String>{};

    if (_ownerNameController.text.trim().isEmpty) {
      errors['owner_name'] = 'Enter your name.';
    }

    if (!_ownerEmailController.text.contains('@')) {
      errors['owner_email'] = 'Enter a valid email address.';
    }

    if (_businessNameController.text.trim().isEmpty) {
      errors['business_name'] = 'Enter your business name.';
    }

    if (_businessType == null) {
      errors['business_type'] = 'Choose what kind of business this is.';
    }

    if (_country == null) {
      errors['country'] = 'Choose your country.';
    }

    if (_currency == null) {
      errors['currency'] = 'Choose the currency you sell in.';
    }

    if (_signupPasswordController.text.length < 8) {
      errors['password'] = 'Use at least 8 characters.';
    } else if (_signupPasswordController.text != _signupPasswordConfirmController.text) {
      errors['password_confirmation'] = 'Both passwords must match.';
    }

    return errors;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      body: Center(
        child: ConstrainedBox(
          // Taller than before: the sign-up side has eight fields, and at
          // 640 they were squeezed into a scroll view inside a box with
          // empty space around it.
          constraints: const BoxConstraints(maxWidth: 960, maxHeight: 720),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Card(
              clipBehavior: Clip.antiAlias,
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final wide = constraints.maxWidth > 720;

                  if (!wide) {
                    return _formArea(theme);
                  }

                  final panelWidth = constraints.maxWidth * 0.42;
                  final formWidth = constraints.maxWidth - panelWidth;

                  // Both halves are positioned and both animate.
                  //
                  // The form used to sit in a Row with a fixed spacer on
                  // the left, while only the panel moved — so on sign-up
                  // the panel slid right and landed on top of the form,
                  // which stayed put and was left with a blank column
                  // beside it. Moving the two together is what makes them
                  // swap sides instead of collide.
                  return Stack(
                    children: [
                      AnimatedPositioned(
                        duration: _slide,
                        curve: Curves.easeInOutCubic,
                        left: _signUp ? 0 : panelWidth,
                        top: 0,
                        bottom: 0,
                        width: formWidth,
                        child: _formArea(theme),
                      ),
                      AnimatedPositioned(
                        duration: _slide,
                        curve: Curves.easeInOutCubic,
                        left: _signUp ? formWidth : 0,
                        top: 0,
                        bottom: 0,
                        width: panelWidth,
                        child: _WelcomePanel(
                          signUp: _signUp,
                          onSwitch: () => _switchTo(signUp: !_signUp),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// The form half, whichever form that currently is.
  ///
  /// Scrollable because the sign-up side is eight fields tall and a
  /// shorter window must not put "Continue" out of reach — a form you
  /// cannot submit is worse than one you have to scroll.
  Widget _formArea(ThemeData theme) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          padding: const EdgeInsets.all(32),
          child: ConstrainedBox(
            // Centres the two-field sign-in form instead of pinning it to
            // the top of a tall card, while still letting the eight-field
            // sign-up form grow past the card and scroll.
            constraints: BoxConstraints(minHeight: constraints.maxHeight - 64),
            child: Center(
              child: AnimatedSwitcher(
                duration: _slide,
                child: _signUp ? _buildSignUpForm(theme) : _buildSignInForm(theme),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildSignInForm(ThemeData theme) {
    return Column(
      key: const ValueKey('sign-in'),
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Sign in', style: theme.textTheme.headlineSmall),
        const SizedBox(height: 4),
        Text(
          'Use the same email and password as the web dashboard.',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
        ),
        const SizedBox(height: 24),
        _field(
          controller: _emailController,
          label: 'Email',
          errorKey: 'email',
          keyboardType: TextInputType.emailAddress,
        ),
        const SizedBox(height: 12),
        _field(
          controller: _passwordController,
          label: 'Password',
          errorKey: 'password',
          obscure: true,
          onSubmitted: (_) => _signIn(),
        ),
        _errorBanner(theme),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: _submitting ? null : _signIn,
          child: _submitting ? const _ButtonSpinner() : const Text('Sign in'),
        ),
        const SizedBox(height: 8),
        TextButton(
          onPressed: () => _switchTo(signUp: true),
          child: const Text('New here? Create a business account'),
        ),
      ],
    );
  }

  Widget _buildSignUpForm(ThemeData theme) {
    return Column(
      key: const ValueKey('sign-up'),
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Create your business', style: theme.textTheme.headlineSmall),
        const SizedBox(height: 4),
        Text(
          'You will choose a product key or a free trial on the next step.',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
        ),
        const SizedBox(height: 24),
        _field(controller: _ownerNameController, label: 'Your name', errorKey: 'owner_name'),
        const SizedBox(height: 12),
        _field(
          controller: _ownerEmailController,
          label: 'Email',
          errorKey: 'owner_email',
          keyboardType: TextInputType.emailAddress,
        ),
        const SizedBox(height: 12),
        _field(controller: _businessNameController, label: 'Business name', errorKey: 'business_name'),
        const SizedBox(height: 12),
        _businessTypeField(),
        const SizedBox(height: 12),
        _countryField(),
        const SizedBox(height: 12),
        _currencyField(),
        const SizedBox(height: 12),
        _field(controller: _signupPasswordController, label: 'Password', errorKey: 'password', obscure: true),
        const SizedBox(height: 12),
        _field(
          controller: _signupPasswordConfirmController,
          label: 'Confirm password',
          errorKey: 'password_confirmation',
          obscure: true,
        ),
        _errorBanner(theme),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: _submitting ? null : _continueSignUp,
          child: const Text('Continue'),
        ),
        const SizedBox(height: 8),
        TextButton(
          onPressed: () => _switchTo(signUp: false),
          child: const Text('Already have an account? Sign in'),
        ),
      ],
    );
  }

  Widget _businessTypeField() {
    return _optionsDropdown(
      label: 'Business type',
      errorKey: 'business_type',
      value: _businessType,
      itemsFrom: (options) => [
        for (final type in options.businessTypes)
          DropdownMenuItem(value: type.slug, child: Text(type.name)),
      ],
      onChanged: (value, _) => setState(() => _businessType = value),
    );
  }

  Widget _countryField() {
    return _optionsDropdown(
      label: 'Country',
      errorKey: 'country',
      value: _country,
      itemsFrom: (options) => [
        for (final country in options.countries)
          DropdownMenuItem(value: country.code, child: Text(country.name)),
      ],
      onChanged: (value, options) => setState(() {
        _country = value;

        // Filled in from the country, but still editable — a business in
        // Kenya may well price in USD. Only prefilled while untouched, so
        // choosing a currency and then changing country does not quietly
        // overwrite a deliberate choice.
        final suggested = options.countryByCode(value)?.defaultCurrencyCode;

        if (_currency == null && suggested != null) {
          _currency = suggested;
        }
      }),
    );
  }

  Widget _currencyField() {
    return _optionsDropdown(
      label: 'Currency',
      errorKey: 'currency',
      value: _currency,
      itemsFrom: (options) => [
        for (final currency in options.currencies)
          DropdownMenuItem(value: currency.code, child: Text(currency.label)),
      ],
      onChanged: (value, _) => setState(() => _currency = value),
    );
  }

  /// Every dropdown on this form is filled from the same one request, so
  /// they share a builder — including the failure case, which has to say
  /// what went wrong. Without the list there is no valid value to submit,
  /// and rendering an ordinary empty field sends someone round a loop
  /// they cannot get out of.
  Widget _optionsDropdown({
    required String label,
    required String errorKey,
    required String? value,
    required List<DropdownMenuItem<String>> Function(RegistrationOptions) itemsFrom,
    required void Function(String?, RegistrationOptions) onChanged,
  }) {
    return FutureBuilder<RegistrationOptions>(
      future: _optionsFuture,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return InputDecorator(
            decoration: InputDecoration(
              labelText: label,
              border: const OutlineInputBorder(),
              errorText: 'Could not load this list. Check your connection to the server.',
            ),
            child: const SizedBox(height: 20),
          );
        }

        final loading = !snapshot.hasData;

        return DropdownButtonFormField<String>(
          initialValue: value,
          isExpanded: true,
          decoration: InputDecoration(
            labelText: label,
            border: const OutlineInputBorder(),
            errorText: _fieldErrors[errorKey],
            suffixIcon: loading
                ? const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(
                      height: 16,
                      width: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                : null,
          ),
          items: loading ? const [] : itemsFrom(snapshot.data!),
          onChanged: loading ? null : (v) => onChanged(v, snapshot.data!),
        );
      },
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String label,
    required String errorKey,
    bool obscure = false,
    TextInputType? keyboardType,
    void Function(String)? onSubmitted,
  }) {
    return TextField(
      controller: controller,
      obscureText: obscure,
      keyboardType: keyboardType,
      onSubmitted: onSubmitted,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
        // Server-side validation errors land on the field that caused
        // them. They used to be collapsed into one line at the bottom of
        // the form, which on an eight-field form is a guessing game.
        errorText: _fieldErrors[errorKey],
      ),
    );
  }

  Widget _errorBanner(ThemeData theme) {
    if (_error == null) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.error_outline, size: 18, color: theme.colorScheme.error),
          const SizedBox(width: 8),
          Expanded(
            child: Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
          ),
        ],
      ),
    );
  }
}

class _ButtonSpinner extends StatelessWidget {
  const _ButtonSpinner();

  @override
  Widget build(BuildContext context) {
    return const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2));
  }
}

class _WelcomePanel extends StatelessWidget {
  const _WelcomePanel({required this.signUp, required this.onSwitch});

  final bool signUp;
  final VoidCallback onSwitch;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      color: theme.colorScheme.primary,
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.storefront_outlined, size: 40, color: theme.colorScheme.onPrimary),
          const SizedBox(height: 24),
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 240),
            child: Column(
              key: ValueKey(signUp),
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  signUp ? 'Already selling with us?' : 'New to BiasharaMax?',
                  style: theme.textTheme.headlineSmall?.copyWith(color: theme.colorScheme.onPrimary),
                ),
                const SizedBox(height: 12),
                Text(
                  signUp
                      ? 'Sign in with your existing account and pick up where you left off.'
                      : 'Set up your business in a couple of minutes and try it free.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onPrimary.withValues(alpha: 0.85),
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          OutlinedButton(
            onPressed: onSwitch,
            style: OutlinedButton.styleFrom(
              foregroundColor: theme.colorScheme.onPrimary,
              side: BorderSide(color: theme.colorScheme.onPrimary.withValues(alpha: 0.7)),
            ),
            child: Text(signUp ? 'Sign in' : 'Create an account'),
          ),
        ],
      ),
    );
  }
}
