import '../../core/api/api_client.dart';
import '../../core/api/endpoints.dart';
import 'auth_api.dart';
import 'entitlement.dart';

class SubscriptionPlanOption {
  const SubscriptionPlanOption({
    required this.id,
    required this.name,
    required this.trialDays,
    this.description,
  });

  factory SubscriptionPlanOption.fromJson(Map<String, dynamic> json) {
    return SubscriptionPlanOption(
      id: json['id'] as String,
      name: json['name'] as String,
      trialDays: (json['trial_days'] as num?)?.toInt() ?? 0,
      description: json['description'] as String?,
    );
  }

  final String id;
  final String name;
  final int trialDays;
  final String? description;
}

class BusinessTypeOption {
  const BusinessTypeOption({required this.slug, required this.name});

  factory BusinessTypeOption.fromJson(Map<String, dynamic> json) {
    return BusinessTypeOption(slug: json['slug'] as String, name: json['name'] as String);
  }

  final String slug;
  final String name;
}

class CountryOption {
  const CountryOption({
    required this.code,
    required this.name,
    this.defaultCurrencyCode,
  });

  factory CountryOption.fromJson(Map<String, dynamic> json) {
    return CountryOption(
      code: json['code'] as String,
      name: json['name'] as String,
      defaultCurrencyCode: json['default_currency_code'] as String?,
    );
  }

  final String code;
  final String name;

  /// Lets picking a country fill in the currency, which is the field that
  /// actually matters and the one nobody reads.
  final String? defaultCurrencyCode;
}

class CurrencyOption {
  const CurrencyOption({required this.code, required this.name, this.symbol});

  factory CurrencyOption.fromJson(Map<String, dynamic> json) {
    return CurrencyOption(
      code: json['code'] as String,
      name: json['name'] as String,
      symbol: json['symbol'] as String?,
    );
  }

  final String code;
  final String name;
  final String? symbol;

  String get label => symbol == null ? '$code — $name' : '$code — $name ($symbol)';
}

class RegistrationOptions {
  const RegistrationOptions({
    required this.plans,
    required this.businessTypes,
    required this.countries,
    required this.currencies,
  });

  final List<SubscriptionPlanOption> plans;
  final List<BusinessTypeOption> businessTypes;
  final List<CountryOption> countries;
  final List<CurrencyOption> currencies;

  CountryOption? countryByCode(String? code) {
    for (final country in countries) {
      if (country.code == code) {
        return country;
      }
    }

    return null;
  }

  /// How long the free trial actually runs, taken from the plan the
  /// server would start it on rather than hard-coded to 30.
  ///
  /// The button says "Start 30-day trial" only when that is true. An
  /// operator who shortens the trial on the platform should not have to
  /// find out that the desktop app has been promising 30 days regardless.
  int? get trialDays {
    for (final plan in plans) {
      if (plan.trialDays > 0) {
        return plan.trialDays;
      }
    }

    return null;
  }
}

/// Sign-up. Separate from AuthApi because signing up creates a business
/// and everything under it, while logging in only proves who you are.
class RegistrationApi {
  RegistrationApi(this._client);

  final ApiClient _client;

  Future<RegistrationOptions> options() async {
    final response = await _client.get(Endpoints.authRegisterOptions);
    final data = response.data as Map<String, dynamic>;

    return RegistrationOptions(
      plans: (data['plans'] as List<dynamic>)
          .map((e) => SubscriptionPlanOption.fromJson(e as Map<String, dynamic>))
          .toList(),
      businessTypes: (data['business_types'] as List<dynamic>)
          .map((e) => BusinessTypeOption.fromJson(e as Map<String, dynamic>))
          .toList(),
      countries: (data['countries'] as List<dynamic>? ?? [])
          .map((e) => CountryOption.fromJson(e as Map<String, dynamic>))
          .toList(),
      currencies: (data['currencies'] as List<dynamic>? ?? [])
          .map((e) => CurrencyOption.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  /// One call creates the owner, the business and its subscription.
  ///
  /// [registrationCode] carries a purchased plan; omitting it starts the
  /// free trial. Both go through the same endpoint so the business is
  /// created once, with the right subscription, in one transaction —
  /// rather than created on a trial and upgraded after, which leaves a
  /// half-provisioned business behind every time the second step fails.
  Future<RegistrationResult> register({
    required String ownerName,
    required String ownerEmail,
    required String password,
    required String passwordConfirmation,
    required String businessName,
    required String businessType,
    required String country,
    required String currency,
    required String deviceName,
    String? ownerPhone,
    String? registrationCode,
  }) async {
    final response = await _client.post(Endpoints.authRegister, data: {
      'owner_name': ownerName,
      'owner_email': ownerEmail,
      'owner_phone': ownerPhone,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'business_name': businessName,
      'business_type': businessType,
      'country': country,
      'currency': currency,
      'device_name': deviceName,
      if (registrationCode != null && registrationCode.isNotEmpty)
        'registration_code': registrationCode,
    });

    final data = response.data as Map<String, dynamic>;

    return RegistrationResult(
      token: data['token'] as String,
      user: AuthenticatedUser.fromJson(data['user'] as Map<String, dynamic>),
      entitlement: Entitlement.fromJson(data['entitlement'] as Map<String, dynamic>),
    );
  }
}

class RegistrationResult {
  const RegistrationResult({
    required this.token,
    required this.user,
    required this.entitlement,
  });

  final String token;
  final AuthenticatedUser user;
  final Entitlement entitlement;
}
