<?php

namespace App\Domain\CRM\Listeners;

use App\Domain\CRM\Services\LoyaltyTierService;
use App\Domain\Sales\Events\SaleCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecalculateLoyaltyTierOnSale implements ShouldQueue
{
    public function __construct(
        private readonly LoyaltyTierService $tierService,
    ) {}

    public function handle(SaleCompleted $event): void
    {
        $customer = $event->sale->customer;

        if ($customer) {
            $this->tierService->recalculateTier($customer);
        }
    }
}
