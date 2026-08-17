<?php

namespace App\Domain\Finance\Events;

use App\Domain\Finance\Models\JournalEntry;
use Illuminate\Foundation\Events\Dispatchable;

class JournalEntryPosted
{
    use Dispatchable;

    public function __construct(
        public readonly JournalEntry $journalEntry,
    ) {}
}
