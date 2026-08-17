<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\Services\AutoPostingService;
use App\Domain\Sales\Events\SaleVoided;

class PostJournalEntryForSaleVoided
{
    public function __construct(
        private readonly AutoPostingService $autoPosting,
    ) {}

    public function handle(SaleVoided $event): void
    {
        $this->autoPosting->postSaleVoided($event->sale);
    }
}
