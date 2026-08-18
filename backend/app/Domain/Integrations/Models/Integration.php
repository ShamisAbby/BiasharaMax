<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Shared\Concerns\Auditable;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Integration extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    public const CATEGORY_OAUTH = 'oauth';

    public const CATEGORY_MAPS = 'maps';

    public const CATEGORY_ANALYTICS = 'analytics';

    public const CATEGORY_SOCIAL_LOGIN = 'social_login';

    public const CATEGORY_AI = 'ai';

    public const CATEGORY_COMMUNICATION = 'communication';

    public const CATEGORY_AUTOMATION = 'automation';

    public const CATEGORY_STORAGE = 'storage';

    public const CATEGORY_CUSTOM = 'custom';

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_PRODUCTION = 'production';

    /**
     * Canonicalises a provider name or credential key for lookup.
     *
     * Both are typed by hand in the admin UI, so they arrive in whatever
     * shape felt natural — "Gemini" for the provider, "API Key" for the
     * credential. Matching those verbatim against the lowercase,
     * underscored identifiers the drivers use fails silently: an
     * unmatched provider quietly falls back to the generic HTTP driver,
     * and an unmatched credential key reads as null. Neither says why.
     *
     * Lowercase, trim, and collapse any run of non-alphanumerics to a
     * single underscore, so `Gemini`, `GEMINI`, `Google Maps` and
     * `API Key` all resolve to what the code expects.
     */
    public static function normalizeKey(?string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $value)) ?? '', '_');
    }

    public const TEST_RESULT_SUCCESS = 'success';

    public const TEST_RESULT_FAILED = 'failed';

    protected $attributes = [
        'is_enabled' => false,
        'mode' => self::MODE_SANDBOX,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'name',
        'slug',
        'category',
        'provider',
        'is_enabled',
        'mode',
        'credentials',
        'webhook_url',
        'last_tested_at',
        'last_test_result',
        'documentation_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'last_tested_at' => 'datetime',
        ];
    }

    protected static function newFactory(): IntegrationFactory
    {
        return IntegrationFactory::new();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class)->latest('created_at');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(\App\Domain\AiInsights\Models\AiInsight::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    /**
     * Has credentials. Says nothing about being switched on — see the long
     * note on PaymentGateway::isConfigured(); this model had the identical
     * deadlock, where the admin would not let you enable an integration
     * until it was configured and it could not be configured until it was
     * enabled.
     */
    public function isConfigured(): bool
    {
        return filled($this->credentials);
    }

    /** Configured AND switched on — what the drivers require. */
    public function isUsable(): bool
    {
        return $this->is_enabled && $this->isConfigured();
    }
}
