<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InventoryImportLog extends Model
{
    use BelongsToTenant, HasUserstamps, HasUuids;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'business_id',
        'file_path',
        'status',
        'total_rows',
        'success_count',
        'failure_count',
        'error_report_path',
    ];
}
