<?php

namespace App\Domain\Inventory\Events;

use App\Domain\Inventory\Models\InventoryImportLog;
use Illuminate\Foundation\Events\Dispatchable;

class BulkImportCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly InventoryImportLog $importLog,
    ) {}
}
