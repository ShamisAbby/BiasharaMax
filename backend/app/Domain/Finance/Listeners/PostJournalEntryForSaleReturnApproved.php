<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\Services\AutoPostingService;
use App\Domain\Sales\Events\SaleReturnApproved;

class PostJournalEntryForSaleReturnApproved
{
    public function __construct(
        private readonly AutoPostingService $autoPosting,
    ) {}

    public function handle(SaleReturnApproved $event): void
    {
        $this->autoPosting->postSaleReturnApproved($event->saleReturn);
    }
}
