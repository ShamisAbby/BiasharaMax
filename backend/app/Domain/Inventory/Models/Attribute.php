<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    public const INPUT_TYPE_SELECT = 'select';

    public const INPUT_TYPE_TEXT = 'text';

    public const INPUT_TYPE_NUMBER = 'number';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'input_type',
        'is_variant_attribute',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_variant_attribute' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
