<?php

namespace App\Providers;

use App\Modules\Business\Models\Branch;
use App\Modules\Business\Models\Business;
use App\Modules\Business\Models\Warehouse;
use App\Modules\Business\Policies\BranchPolicy;
use App\Modules\Business\Policies\BusinessPolicy;
use App\Modules\Business\Policies\WarehousePolicy;
use App\Modules\RBAC\Models\Role;
use App\Modules\RBAC\Policies\RolePolicy;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
    }
}
