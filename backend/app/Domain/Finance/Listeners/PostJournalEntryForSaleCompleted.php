<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\Services\AutoPostingService;
use App\Domain\Sales\Events\SaleCompleted;

class PostJournalEntryForSaleCompleted
{
    public function __construct(
        private readonly AutoPostingService $autoPosting,
    ) {}

    public function handle(SaleCompleted $event): void
    {
        $this->autoPosting->postSaleCompleted($event->sale);
    }
}
