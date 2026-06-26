<?php

namespace App\Modules\Business\Models;

use App\Modules\Shared\Concerns\Auditable;
use App\Modules\Shared\Concerns\BelongsToTenant;
use App\Modules\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'branch_id',
        'name',
        'code',
        'is_default',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
