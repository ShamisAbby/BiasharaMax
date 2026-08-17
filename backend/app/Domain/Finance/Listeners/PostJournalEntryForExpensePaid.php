<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Accounting\Events\ExpenseMarkedPaid;
use App\Domain\Finance\Services\AutoPostingService;

class PostJournalEntryForExpensePaid
{
    public function __construct(
        private readonly AutoPostingService $autoPosting,
    ) {}

    public function handle(ExpenseMarkedPaid $event): void
    {
        $this->autoPosting->postExpensePaid($event->expense);
    }
}
