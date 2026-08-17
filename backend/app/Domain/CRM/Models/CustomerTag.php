<?php

namespace App\Domain\CRM\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomerTag extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'color',
        'created_by',
    ];

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domain\Sales\Models\Customer::class, 'customer_customer_tag');
    }
}
