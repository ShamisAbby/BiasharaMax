<?php

namespace App\Domain\Reports\Providers;

use App\Domain\Reports\Services\ReportCenterService;
use Illuminate\Support\ServiceProvider;

class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReportCenterService::class);
    }
}
