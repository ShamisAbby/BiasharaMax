<?php

namespace App\Http\Middleware;

use App\Domain\Localization\Models\Currency;
use App\Domain\ModuleManagement\Services\BusinessModuleResolver;
use App\Domain\Platform\Services\PlatformStatusBadgeService;
use App\Domain\Platform\Support\AdminSurface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * The current request's flash bag, limited to values that are safe and
     * useful to serialise into page props.
     *
     * @return array<string, scalar|null>
     */
    private function flashedScalars(Request $request): array
    {
        $session = $request->session();

        return collect($session->get('_flash.old', []))
            ->mapWithKeys(fn (string $key): array => [$key => $session->get($key)])
            ->filter(fn ($value): bool => $value === null || is_scalar($value))
            ->all();
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = Auth::guard('web')->user()?->loadMissing(['business.subscription.plan', 'roles.permissions']);
        $platformUser = Auth::guard('platform')->user();

        return [
            ...parent::share($request),
            'auth' => [
                // An explicit shape rather than the whole model.
                //
                // Handing Inertia the User instance serialised every column
                // AND the eager-loaded `roles.permissions` — 171 permission
                // rows for an owner — into the props of every single page,
                // on every request, none of which the UI reads. The
                // permission slugs it does need are below, once, as strings.
                'user' => $user ? [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar_url' => $user->avatar_url,
                    'email_verified_at' => $user->email_verified_at,
                    'business_id' => $user->business_id,
                    'role_id' => $user->role_id,
                ] : null,
                // An explicit shape, for the same reason as `user` above
                // and one more: a Business carries relations whose
                // serialised key collides with a column. `businessType`
                // serialises to `business_type`, which is also a string
                // column — so any code anywhere that lazy-loads that
                // relation silently turns this prop from a string into an
                // object and every page rendering it throws. Listing the
                // fields makes that impossible rather than merely unlikely.
                'business' => $user?->business ? [
                    'id' => $user->business->getKey(),
                    'name' => $user->business->name,
                    'slug' => $user->business->slug,
                    'business_type' => $user->business->getAttributeValue('business_type'),
                    'email' => $user->business->email,
                    'phone' => $user->business->phone,
                    'country' => $user->business->country,
                    'currency' => $user->business->currency,
                    'timezone' => $user->business->timezone,
                    'address' => $user->business->address,
                    'city' => $user->business->city,
                    'logo_path' => $user->business->logo_path,
                    'status' => $user->business->status,
                    'trial_ends_at' => $user->business->trial_ends_at,
                ] : null,
                'subscription' => $user?->business?->subscription,
                // First role only, purely for display. Authorization
                // uses `permissions` below, which is the union across
                // every assigned role.
                //
                // `->only()` matters here: `roles.permissions` is eager
                // loaded just below to build that union, so handing over the
                // Role model itself would drag the whole permission list
                // along with it and undo the trim above.
                'role' => $user?->roles->first()?->only(['id', 'name', 'slug']),
                'roles' => $user?->roles->map->only(['id', 'name', 'slug']) ?? [],
                'permissions' => $user
                    ?->roles
                    ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
                    ->unique()
                    ->values() ?? [],
                // Sections the Super Admin has switched OFF for this
                // business. The negative list travels rather than the
                // positive one so a UI that hasn't been told about a
                // section shows it, instead of hiding everything it
                // doesn't recognise. Routes are gated server-side too —
                // this only stops the menu advertising a dead link.
                'hiddenModules' => app(BusinessModuleResolver::class)->hiddenSlugs($user?->business),
            ],
            // Controllers redirect back with `->with('status', 'supplier-created')`
            // (304 sites) or `->with('success', '...')` (18 more) — and until
            // now none of it was shared, so every one of those confirmations
            // was written to the session and thrown away. Nothing in the UI
            // could see them.
            //
            // Read from `_flash.old` rather than a fixed key list: that is
            // exactly the set of keys flashed by the request that redirected
            // here, so new flash keys work without touching this file.
            //
            // Scalars only. Some controllers flash whole models
            // (`->with('business', $business)`) as view data, and those have
            // no business being serialised into every page's props.
            'flash' => $this->flashedScalars($request),

            'impersonating' => $request->session()->has('impersonation_log_id'),
            'platformAuth' => [
                'user' => $platformUser ? [
                    'id'         => $platformUser->id,
                    'name'       => $platformUser->name,
                    'email'      => $platformUser->email,
                    'avatar_url' => $platformUser->avatar
                        ? Storage::url($platformUser->avatar)
                        : null,
                ] : null,
                // Which admin surface this account lands on, and what it
                // would give up by switching.
                //
                // The list comes from AdminSurface::ONLY_ON rather than
                // being written into the React confirmation dialog,
                // because the day someone ports the permission matrix to
                // Filament the warning has to stop mentioning it. A
                // hardcoded string would keep warning about a screen that
                // is no longer missing, and — worse in the other
                // direction — would stay silent about a new one.
                'surface' => $platformUser ? [
                    'current' => $platformUser->preferredAdminSurface(),
                    'missingFromFilament' => AdminSurface::missingFrom(AdminSurface::FILAMENT),
                ] : null,
                // The same badge the Filament top bar renders, from the
                // same service and the same 60-second cache entry.
                //
                // Only for signed-in platform admins, and only computed
                // then: the service touches the database and Redis, and
                // this middleware runs on every tenant request too.
                'status' => $platformUser
                    ? app(PlatformStatusBadgeService::class)->current()
                    : null,
            ],
            // Real, SuperAdmin-managed exchange rates (see Platform Settings
            // -> Currencies) — shared for both the platform and tenant
            // areas, which both use the same currency switcher.
            'availableCurrencies' => ($platformUser || $user)
                ? Currency::query()->where('is_active', true)->orderByDesc('is_base')->get(['code', 'name', 'symbol', 'exchange_rate_to_base', 'is_base'])
                : [],
        ];
    }
}
