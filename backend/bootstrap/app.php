<?php

use App\Http\Middleware\EnsureBusinessWebsiteIsNotWithdrawn;
use App\Http\Middleware\EnsureModuleIsEnabled;
use App\Http\Middleware\EnsurePlatformPermission;
use App\Http\Middleware\EnsureSubscriptionIsActive;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Domain\Backup\Console\Commands\RunScheduledBackup;
use App\Domain\Finance\Console\Commands\BackfillPurchaseOrderBalances;
use App\Domain\Platform\Console\Commands\DispatchPlatformAlerts;
use App\Domain\Finance\Console\Commands\SeedChartOfAccounts;
use App\Domain\Finance\Console\Commands\SeedOpeningBalancesForBusiness;
use App\Domain\Monitoring\Console\Commands\RecordSystemHealthSnapshot;
use App\Domain\Inventory\Console\Commands\CheckInventoryAlerts;
use App\Domain\Security\Services\LoginSecurityService;
use App\Domain\Subscription\Console\Commands\CheckSubscriptionExpirations;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CheckInventoryAlerts::class,
        CheckSubscriptionExpirations::class,
        DispatchPlatformAlerts::class,
        RecordSystemHealthSnapshot::class,
        RunScheduledBackup::class,
        SeedChartOfAccounts::class,
        BackfillPurchaseOrderBalances::class,
        SeedOpeningBalancesForBusiness::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        Authenticate::redirectUsing(fn ($request) => $request->is('admin/*')
            ? route('platform.login')
            : route('login'));

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // Server-to-server POSTs carry no session and therefore no CSRF
        // token. Without this exclusion every Snippe webhook is rejected
        // with a 419 before the controller runs — and Snippe retries five
        // times into the same wall, so the symptom is "payments never
        // confirm" with nothing in the application log to say why.
        //
        // Safe because the endpoint authenticates by HMAC signature
        // instead: see SnippeSignatureVerifier.
        $middleware->validateCsrfTokens(except: [
            'webhooks/snippe',
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            'subscription.active' => EnsureSubscriptionIsActive::class,
            'platform.permission' => EnsurePlatformPermission::class,
            'website.not-withdrawn' => EnsureBusinessWebsiteIsNotWithdrawn::class,
            // Gates a whole dashboard section. Accepts several slugs —
            // `module:sales,crm` passes if the business has either, which
            // is what shared screens like Customers need.
            'module' => EnsureModuleIsEnabled::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Records a permission_violation security alert whenever an
        // already-authenticated user is refused by an authorization
        // check. Hooked here rather than in the permission middleware
        // because `abort_unless($user->hasPermission(...), 403)` is
        // scattered across dozens of controllers — the exception handler
        // is the one place every path converges on.
        //
        // Only authenticated 403s count. An anonymous 403 says nothing
        // about who was probing, and 404s are excluded so ordinary
        // mistyped URLs don't fill the Security Center with noise.
        // Deliberately `render()`, not `report()`. Laravel's handler
        // short-circuits on `shouldntReport()` BEFORE reportable
        // callbacks run, and its internal ignore list contains both
        // HttpException and AuthorizationException — so a report()
        // callback for these would never fire at all. Render callbacks
        // are not filtered that way, and returning null here falls
        // through to the normal error response untouched.
        //
        // Matching on the HttpException status rather than
        // AccessDeniedHttpException matters too: `abort(403)` throws a
        // PLAIN HttpException, so matching the subclass would miss
        // almost every real call site.
        $exceptions->render(function (HttpException|AuthorizationException $e, $request) {
            if ($e instanceof HttpException && $e->getStatusCode() !== 403) {
                return null;
            }

            $user = Auth::guard('platform')->user() ?? Auth::guard('web')->user();

            // Only authenticated 403s are worth recording — an anonymous
            // one says nothing about who was probing.
            if ($user !== null) {
                // Detection must never be able to break the response
                // itself, so a failure here is swallowed.
                rescue(fn () => app(LoginSecurityService::class)
                    ->recordPermissionViolation($user, $request->path()));
            }

            return null;
        });
    })->create();
