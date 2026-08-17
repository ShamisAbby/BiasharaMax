<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\Services\AutoPostingService;
use App\Domain\Sales\Events\SalePaymentRecorded;

class PostJournalEntryForSalePayment
{
    public function __construct(
        private readonly AutoPostingService $autoPosting,
    ) {}

    public function handle(SalePaymentRecorded $event): void
    {
        $this->autoPosting->postSalePayment($event->salePayment);
    }
}
