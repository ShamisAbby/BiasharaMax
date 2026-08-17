<?php

namespace App\Domain\CRM\Listeners;

use App\Domain\CRM\Services\LoyaltyTierService;
use App\Domain\Sales\Events\SaleReturnApproved;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecalculateLoyaltyTierOnReturn implements ShouldQueue
{
    public function __construct(
        private readonly LoyaltyTierService $tierService,
    ) {}

    public function handle(SaleReturnApproved $event): void
    {
        $customer = $event->saleReturn->customer;

        if ($customer) {
            $this->tierService->recalculateTier($customer);
        }
    }
}
