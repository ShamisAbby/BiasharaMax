<?php

namespace App\Domain\Inventory\Listeners;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Events\BulkImportCompleted;
use App\Domain\Inventory\Notifications\BulkImportCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyUserOfImportCompletion implements ShouldQueue
{
    public function handle(BulkImportCompleted $event): void
    {
        $initiator = User::query()->find($event->importLog->created_by);

        $initiator?->notify(new BulkImportCompletedNotification($event->importLog));
    }
}
