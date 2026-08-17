<?php

namespace App\Domain\CRM\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoyaltyTier extends Model
{
    use Auditable, BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    protected $attributes = [
        'minimum_spend' => 0,
        'minimum_spend_minor' => 0,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'minimum_spend',
        'minimum_spend_minor',
        'sort_order',
        'benefits_description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_spend' => 'decimal:2',
            'minimum_spend_minor' => 'integer',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(\App\Domain\Sales\Models\Customer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return ['minimum_spend' => 'minimum_spend_minor'];
    }
}
