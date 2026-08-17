<?php

namespace App\Providers;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Business\Policies\BranchPolicy;
use App\Domain\Business\Policies\BusinessPolicy;
use App\Domain\Business\Policies\WarehousePolicy;
use App\Domain\RBAC\Models\Role;
use App\Domain\RBAC\Policies\RolePolicy;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Domain\ModuleManagement\Services\BusinessModuleResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // A singleton so the memo inside it is worth having. The module
        // set is consulted by the route middleware, again when the shared
        // props are built, and again for every search source — a fresh
        // instance each time would re-run the same four queries a dozen
        // times per page, which is the exact cost the memo exists to avoid.
        $this->app->singleton(BusinessModuleResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Inertia pages read resource fields directly (e.g. `product.name`,
        // not `product.data.name`) — without this, every single-resource
        // page silently breaks under the default API-style "data" envelope.
        // Collections (paginated grids) are unaffected; they still nest
        // page-button metadata under `meta` as already established.
        JsonResource::withoutWrapping();

        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
    }
}
