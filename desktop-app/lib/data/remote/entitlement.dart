/// What the server says about this business's right to use the app.
///
/// Every field is decided server-side (see DesktopEntitlementService on
/// the Laravel side). The app deliberately holds no rules of its own
/// about trials or licences — it renders whatever it is told, so a
/// revoked licence takes effect on the next launch instead of the next
/// release.
class Entitlement {
  const Entitlement({
    required this.state,
    required this.allowed,
    required this.message,
    required this.canStartTrial,
    required this.requiresProductKey,
    this.planName,
    this.subscriptionStatus,
    this.daysRemaining,
  });

  factory Entitlement.fromJson(Map<String, dynamic> json) {
    final subscription = json['subscription'] as Map<String, dynamic>?;

    return Entitlement(
      state: json['state'] as String? ?? stateUnknown,
      allowed: json['allowed'] == true,
      message: json['message'] as String? ?? '',
      canStartTrial: json['can_start_trial'] == true,
      requiresProductKey: json['requires_product_key'] == true,
      planName: subscription?['plan_name'] as String?,
      subscriptionStatus: subscription?['status'] as String?,
      daysRemaining: subscription?['days_remaining'] as int?,
    );
  }

  /// Used when the server could not be reached at all, so the app can
  /// tell "we don't know" apart from "you are not allowed" — those two
  /// deserve very different screens, and conflating them means a shop
  /// with a flaky connection is told its subscription has expired.
  static const stateUnknown = 'unknown';

  static const Entitlement unknown = Entitlement(
    state: stateUnknown,
    allowed: false,
    message: 'Could not reach the server to check your subscription.',
    canStartTrial: false,
    requiresProductKey: false,
  );

  final String state;
  final bool allowed;
  final String message;
  final bool canStartTrial;
  final bool requiresProductKey;
  final String? planName;
  final String? subscriptionStatus;
  final int? daysRemaining;

  bool get isUnknown => state == stateUnknown;

  /// Worth putting in front of the vendor before it bites. Only for
  /// trials — a paying customer's renewal is not their problem to solve
  /// from a till.
  bool get shouldWarnAboutExpiry =>
      allowed && subscriptionStatus == 'trialing' && (daysRemaining ?? 99) <= 7;
}
