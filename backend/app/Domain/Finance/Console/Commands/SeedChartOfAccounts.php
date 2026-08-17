<?php

namespace App\Domain\Finance\Console\Commands;

use App\Domain\Business\Models\Business;
use App\Domain\Finance\Services\ChartOfAccountsService;
use Illuminate\Console\Command;

/**
 * One-off backfill for businesses that existed before the Finance module
 * shipped. New businesses get their Chart of Accounts seeded automatically
 * at registration (see BusinessRegistrationService::register()).
 */
class SeedChartOfAccounts extends Command
{
    protected $signature = 'finance:seed-chart-of-accounts {business? : A specific business ID; omit to seed every business}';

    protected $description = 'Seed the default Chart of Accounts for one business or all businesses.';

    public function handle(ChartOfAccountsService $chartOfAccountsService): int
    {
        $businessId = $this->argument('business');

        $businesses = $businessId
            ? Business::query()->where('id', $businessId)->get()
            : Business::query()->get();

        if ($businesses->isEmpty()) {
            $this->error('No matching business found.');

            return self::FAILURE;
        }

        $businesses->each(function (Business $business) use ($chartOfAccountsService) {
            $chartOfAccountsService->seedDefaults($business->getKey());
            $this->info("Seeded Chart of Accounts for \"{$business->name}\".");
        });

        return self::SUCCESS;
    }
}
