<?php

return [
    App\Domain\Accounting\Providers\AccountingServiceProvider::class,
    App\Domain\CRM\Providers\CrmServiceProvider::class,
    App\Domain\Finance\Providers\FinanceServiceProvider::class,
    App\Domain\Inventory\Providers\InventoryServiceProvider::class,
    App\Domain\Payroll\Providers\PayrollServiceProvider::class,
    App\Domain\Purchasing\Providers\PurchasingServiceProvider::class,
    App\Domain\Reports\Providers\ReportsServiceProvider::class,
    App\Domain\Sales\Providers\SalesServiceProvider::class,
    App\Domain\Website\Providers\WebsiteServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\PlatformPanelProvider::class,
    App\Providers\HorizonServiceProvider::class,
];
