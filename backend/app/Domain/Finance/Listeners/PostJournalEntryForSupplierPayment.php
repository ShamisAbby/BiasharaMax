<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\Services\AutoPostingService;
use App\Domain\Purchasing\Events\SupplierPaymentRecorded;

class PostJournalEntryForSupplierPayment
{
    public function __construct(
        private readonly AutoPostingService $autoPosting,
    ) {}

    public function handle(SupplierPaymentRecorded $event): void
    {
        $this->autoPosting->postSupplierPayment($event->supplierPayment);
    }
}
