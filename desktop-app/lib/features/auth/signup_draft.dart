import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Sign-up details collected but not yet submitted.
///
/// The vendor fills in the form, then chooses a product key or a free
/// trial on the next screen, and only then does anything get created.
/// Holding the draft here is what makes that one atomic request possible.
///
/// It is in memory only, and deliberately so — the password is in it. If
/// the app is closed between the two screens the form is filled in again,
/// which is a smaller cost than a plaintext password sitting in local
/// storage waiting for a step that may never come.
class SignupDraft {
  const SignupDraft({
    required this.ownerName,
    required this.ownerEmail,
    required this.businessName,
    required this.businessType,
    required this.country,
    required this.currency,
    required this.password,
    required this.passwordConfirmation,
    this.ownerPhone,
  });

  final String ownerName;
  final String ownerEmail;
  final String businessName;
  final String businessType;
  final String country;
  final String currency;
  final String password;
  final String passwordConfirmation;
  final String? ownerPhone;
}

/// Null whenever the user arrived at the activation screen by signing in
/// rather than signing up — which is exactly how that screen tells the
/// two cases apart.
final signupDraftProvider = StateProvider<SignupDraft?>((ref) => null);
