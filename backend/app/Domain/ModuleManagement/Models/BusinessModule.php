<?php

namespace App\Domain\ModuleManagement\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The real per-tenant module activation record — richer than a plain
 * pivot since it tracks its own enabled state and install/uninstall
 * timestamps independently of the catalog row.
 */
class BusinessModule extends Model
{
    use Auditable, HasUserstamps, HasUuids;

    protected $table = 'business_module';

    protected $fillable = [
        'business_id',
        'module_id',
        'is_enabled',
        'installed_at',
        'uninstalled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
