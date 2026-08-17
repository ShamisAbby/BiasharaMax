/// Mirrors routes/api.php on the Laravel side. Keeping these in one place
/// means a route rename on the backend is a one-line fix here, not a
/// find-and-replace across the app.
class Endpoints {
  static const licenseActivate = 'v1/licenses/activate';
  static const licenseValidate = 'v1/licenses/validate';

  static const authLogin = 'v1/auth/login';
  static const authLogout = 'v1/auth/logout';
  static const authMe = 'v1/auth/me';
  static const authRegister = 'v1/auth/register';
  static const authRegisterOptions = 'v1/auth/register/options';

  static const entitlement = 'v1/entitlement';

  static const locations = 'v1/locations';

  static const syncProductsPull = 'v1/sync/products';
  static const syncSalesPush = 'v1/sync/sales';
}
