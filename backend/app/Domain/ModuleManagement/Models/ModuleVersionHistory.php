<?php

namespace App\Domain\ModuleManagement\Models;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only — "Update History". Never updated, only inserted.
 */
class ModuleVersionHistory extends Model
{
    use HasUuids;

    protected $table = 'module_version_history';

    public const UPDATED_AT = null;

    protected $fillable = [
        'module_id',
        'from_version',
        'to_version',
        'changed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'changed_by');
    }
}
