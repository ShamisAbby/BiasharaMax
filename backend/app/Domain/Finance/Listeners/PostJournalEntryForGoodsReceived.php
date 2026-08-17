<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\Services\AutoPostingService;
use App\Domain\Purchasing\Events\GoodsReceived;

class PostJournalEntryForGoodsReceived
{
    public function __construct(
        private readonly AutoPostingService $autoPosting,
    ) {}

    public function handle(GoodsReceived $event): void
    {
        $this->autoPosting->postGoodsReceived($event->goodsReceivedNote);
    }
}
