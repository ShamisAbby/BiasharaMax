<?php

namespace App\Domain\Inventory\Jobs;

use App\Domain\Inventory\Models\InventoryImportLog;
use App\Domain\Inventory\Services\InventoryImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInventoryImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly InventoryImportLog $importLog,
    ) {}

    public function handle(InventoryImportService $service): void
    {
        $service->import($this->importLog);
    }
}
