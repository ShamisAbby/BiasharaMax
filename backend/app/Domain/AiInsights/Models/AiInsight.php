<?php

namespace App\Domain\AiInsights\Models;

use App\Domain\Integrations\Models\Integration;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsight extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const TYPE_REVENUE_FORECAST = 'revenue_forecast';

    public const TYPE_SUBSCRIPTION_FORECAST = 'subscription_forecast';

    public const TYPE_CHURN_RISK = 'churn_risk';

    public const TYPE_BUSINESS_HEALTH = 'business_health';

    public const TYPE_GROWTH_TREND = 'growth_trend';

    public const TYPE_RECOMMENDATION = 'recommendation';

    public const GENERATED_BY_RULE_BASED = 'rule_based';

    public const GENERATED_BY_AI_PROVIDER = 'ai_provider';

    protected $attributes = [
        'generated_by' => self::GENERATED_BY_RULE_BASED,
        'is_read' => false,
    ];

    protected $fillable = [
        'type',
        'title',
        'summary',
        'data',
        'generated_by',
        'integration_id',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
