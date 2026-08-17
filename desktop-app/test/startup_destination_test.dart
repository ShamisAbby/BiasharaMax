import 'package:biasharamax_desktop/data/remote/entitlement.dart';
import 'package:biasharamax_desktop/data/repositories/auth_repository.dart';
import 'package:flutter_test/flutter_test.dart';

Entitlement _entitlement({
  required bool allowed,
  String state = 'allowed',
  String? status,
  int? daysRemaining,
}) {
  return Entitlement(
    state: state,
    allowed: allowed,
    message: '',
    canStartTrial: false,
    requiresProductKey: false,
    subscriptionStatus: status,
    daysRemaining: daysRemaining,
  );
}

void main() {
  group('startupDestinationFor', () {
    test('a fresh install sees the intro', () {
      expect(
        startupDestinationFor(introSeen: false, hasSession: false, entitlement: null),
        StartupDestination.intro,
      );
    });

    test('the intro is not shown again once it has been through', () {
      expect(
        startupDestinationFor(introSeen: true, hasSession: false, entitlement: null),
        StartupDestination.auth,
      );
    });

    test('an entitled session opens the dashboard', () {
      expect(
        startupDestinationFor(
          introSeen: true,
          hasSession: true,
          entitlement: _entitlement(allowed: true),
        ),
        StartupDestination.dashboard,
      );
    });

    test('a lapsed subscription is sent to activation, not to login', () {
      // Signing someone out because their trial ended tells them the
      // wrong thing — their password is fine, their subscription is not.
      expect(
        startupDestinationFor(
          introSeen: true,
          hasSession: true,
          entitlement: _entitlement(allowed: false, state: 'locked'),
        ),
        StartupDestination.activation,
      );
    });

    test('an unreachable server does not shut the till', () {
      // The single most important case. "We could not ask" must not be
      // treated as "you are not allowed": a shop with a dropped
      // connection would otherwise be locked out of an offline-capable
      // POS by the check meant to protect it.
      expect(
        startupDestinationFor(
          introSeen: true,
          hasSession: true,
          entitlement: Entitlement.unknown,
        ),
        StartupDestination.dashboard,
      );
    });

    test('the intro comes before the entitlement check', () {
      // Order matters: a first-time user with a stale token should still
      // be introduced to the product before being told about billing.
      expect(
        startupDestinationFor(
          introSeen: false,
          hasSession: true,
          entitlement: _entitlement(allowed: false, state: 'locked'),
        ),
        StartupDestination.intro,
      );
    });
  });

  group('Entitlement', () {
    test('reads the server response', () {
      final entitlement = Entitlement.fromJson({
        'state': 'allowed',
        'allowed': true,
        'message': 'Subscription active.',
        'can_start_trial': false,
        'requires_product_key': false,
        'subscription': {
          'status': 'trialing',
          'plan_name': 'Starter',
          'days_remaining': 12,
        },
      });

      expect(entitlement.allowed, isTrue);
      expect(entitlement.planName, 'Starter');
      expect(entitlement.daysRemaining, 12);
    });

    test('a missing subscription block does not throw', () {
      // The server sends `subscription: null` for an account with no
      // business at all, and a crash on the launch path is a worse
      // outcome than any message that screen could show.
      final entitlement = Entitlement.fromJson({
        'state': 'no_subscription',
        'allowed': false,
        'message': 'No subscription.',
        'can_start_trial': true,
        'requires_product_key': false,
        'subscription': null,
      });

      expect(entitlement.allowed, isFalse);
      expect(entitlement.canStartTrial, isTrue);
      expect(entitlement.daysRemaining, isNull);
    });

    test('warns near the end of a trial', () {
      expect(
        _entitlement(allowed: true, status: 'trialing', daysRemaining: 3).shouldWarnAboutExpiry,
        isTrue,
      );
    });

    test('does not nag a paying customer about their renewal date', () {
      // Renewal is not something a cashier can act on from a till, and a
      // banner that is always up is a banner nobody reads on the day it
      // matters.
      expect(
        _entitlement(allowed: true, status: 'active', daysRemaining: 3).shouldWarnAboutExpiry,
        isFalse,
      );
    });

    test('an offline result is not mistaken for an expiry warning', () {
      expect(Entitlement.unknown.shouldWarnAboutExpiry, isFalse);
      expect(Entitlement.unknown.isUnknown, isTrue);
    });
  });
}
