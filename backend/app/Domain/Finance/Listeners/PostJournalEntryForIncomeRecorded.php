<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Accounting\Events\IncomeRecorded;
use App\Domain\Finance\Services\AutoPostingService;

class PostJournalEntryForIncomeRecorded
{
    public function __construct(
        private readonly AutoPostingService $autoPosting,
    ) {}

    public function handle(IncomeRecorded $event): void
    {
        $this->autoPosting->postIncomeRecorded($event->income);
    }
}
