<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EntitlementController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\SyncController;
use App\Domain\Licensing\Http\Controllers\LicenseValidationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Called by the BiasharaMax Desktop Edition client — unauthenticated by
// necessity (no web session exists during installer activation), so it's
// rate-limited instead. One activation per installer run, then the
// desktop app moves on to /v1/auth/login for the actual employee session.
Route::prefix('v1/licenses')->middleware('throttle:30,1')->group(function () {
    Route::post('activate', [LicenseValidationController::class, 'activate'])->name('api.licenses.activate');
    Route::post('validate', [LicenseValidationController::class, 'validateLicense'])->name('api.licenses.validate');
});

// Employee auth for non-browser clients (Flutter Desktop today). Kept
// deliberately separate from license activation above — a license
// activates the installation once per device; this authenticates an
// individual employee on that installation, same as the web app's
// session guard does for the browser.
Route::prefix('v1/auth')->middleware('throttle:20,1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('api.auth.login');

    // Sign-up from the desktop client. Rate-limited harder than login:
    // each successful call creates a business, an owner, a branch and a
    // chart of accounts, so an unthrottled loop here fills the database
    // rather than merely guessing at a password.
    Route::get('register/options', [RegistrationController::class, 'options'])->name('api.auth.register.options');
    Route::post('register', [RegistrationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('api.auth.register');
});

Route::prefix('v1/auth')->middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('api.auth.me');
});

// Whether this business may use the app, decided server-side. See
// DesktopEntitlementService for why it is not the client's call.
Route::prefix('v1')->middleware(['auth:sanctum', 'ability:desktop'])->group(function () {
    Route::get('entitlement', [EntitlementController::class, 'show'])->name('api.entitlement.show');
});

// Sync surface — token must carry the 'desktop' ability minted at login
// (see AuthController::login()); a leaked token scoped that way can't be
// used to reach anything outside this group.
Route::prefix('v1/sync')->middleware(['auth:sanctum', 'ability:desktop'])->group(function () {
    Route::get('products', [SyncController::class, 'pullProducts'])->name('api.sync.products.pull');
    Route::post('sales', [SyncController::class, 'pushSales'])->name('api.sync.sales.push');
});

// Branches and their warehouses, so a till can be pointed at a real place
// by name instead of by UUID — and so checkout has a branch_id even when
// the signed-in employee has none of their own.
Route::prefix('v1')->middleware(['auth:sanctum', 'ability:desktop'])->group(function () {
    Route::get('locations', [LocationController::class, 'index'])->name('api.locations.index');
});
