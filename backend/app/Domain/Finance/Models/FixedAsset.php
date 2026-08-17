<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasUserstamps;
use App\Domain\Shared\Concerns\SyncsMoneyMinorColumns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use BelongsToTenant, HasUserstamps, HasUuids, SoftDeletes, SyncsMoneyMinorColumns;

    public const CATEGORY_LAND = 'land';

    public const CATEGORY_BUILDING = 'building';

    public const CATEGORY_VEHICLE = 'vehicle';

    public const CATEGORY_EQUIPMENT = 'equipment';

    public const CATEGORY_FURNITURE = 'furniture';

    public const CATEGORY_INTANGIBLE = 'intangible';

    public const CATEGORY_OTHER = 'other';

    public const METHOD_STRAIGHT_LINE = 'straight_line';

    public const METHOD_DECLINING_BALANCE = 'declining_balance';

    public const METHOD_NONE = 'none';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';

    public const STATUS_DISPOSED = 'disposed';

    protected $fillable = [
        'business_id',
        'asset_code',
        'asset_name',
        'category',
        'acquisition_date',
        'acquisition_cost',
        'acquisition_cost_minor',
        'account_id',
        'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
        'useful_life_months',
        'residual_value',
        'residual_value_minor',
        'depreciation_method',
        'status',
        'disposal_date',
        'disposal_proceeds',
        'disposal_proceeds_minor',
        'disposal_journal_entry_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'acquisition_cost_minor' => 'integer',
            'residual_value' => 'decimal:2',
            'residual_value_minor' => 'integer',
            'disposal_date' => 'date',
            'disposal_proceeds' => 'decimal:2',
            'disposal_proceeds_minor' => 'integer',
            'useful_life_months' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    public function depreciationSchedule(): HasMany
    {
        return $this->hasMany(DepreciationSchedule::class);
    }

    public function disposalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'disposal_journal_entry_id');
    }

    public function currentBookValue(): string
    {
        $totalDepreciation = (string) $this->depreciationSchedule()
            ->where('status', DepreciationSchedule::STATUS_POSTED)
            ->sum('depreciation_amount');

        return bcsub((string) $this->acquisition_cost, $totalDepreciation, 2);
    }

    public function totalAccumulatedDepreciation(): string
    {
        return (string) $this->depreciationSchedule()
            ->where('status', DepreciationSchedule::STATUS_POSTED)
            ->sum('depreciation_amount');
    }

    /**
     * @return array<string, string>
     */
    protected function moneyMinorColumns(): array
    {
        return [
            'acquisition_cost' => 'acquisition_cost_minor',
            'residual_value' => 'residual_value_minor',
            'disposal_proceeds' => 'disposal_proceeds_minor',
        ];
    }
}
