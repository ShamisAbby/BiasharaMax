<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Shared\Concerns\Auditable;
use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationChannel extends Model
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_PUSH = 'push';

    public const CHANNEL_IN_APP = 'in_app';

    protected $attributes = [
        'is_enabled' => false,
        'is_default' => false,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'name',
        'channel',
        'provider',
        'is_enabled',
        'is_default',
        'credentials',
        'sender_id',
        'webhook_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    protected static function newFactory(): NotificationChannelFactory
    {
        return NotificationChannelFactory::new();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    /**
     * Whether this channel has everything it needs to deliver.
     *
     * Two things were wrong with the previous
     * `is_enabled && filled($this->credentials)`:
     *
     * 1. **It required `is_enabled`**, which made the state circular. The
     *    intended workflow — and what the UI tells an operator to do — is
     *    configure a channel and *then* enable it. Under the old rule a
     *    disabled channel could never report as configured, so "configure
     *    first" was impossible to satisfy or to see.
     *
     * 2. **It required credentials**, which email and in-app deliberately
     *    do not have. EmailDriver uses Laravel's own mail transport from
     *    config/mail.php, and InAppDriver just writes a database
     *    notification. Both were therefore permanently "Not configured"
     *    no matter what was in `.env` — which is exactly the report that
     *    prompted this.
     *
     * Configured and enabled are now orthogonal, which is what they
     * describe: one is "could this work", the other is "should it run".
     */
    public function isConfigured(): bool
    {
        return match ($this->channel) {
            // Nothing external to configure — it writes to this database.
            self::CHANNEL_IN_APP => true,

            // Delegates entirely to the application's mail transport.
            self::CHANNEL_EMAIL => $this->applicationMailerCanDeliver(),

            // Everything else talks to a third party and needs keys.
            default => filled($this->credentials),
        };
    }

    /**
     * A sentence explaining why this channel cannot deliver, or null.
     *
     * The card previously showed a bare "Not configured", which names the
     * state without naming the fix — and for email the fix is not even on
     * that screen, it is in `.env`.
     */
    public function configurationHint(): ?string
    {
        if ($this->isConfigured()) {
            return null;
        }

        if ($this->channel === self::CHANNEL_EMAIL) {
            return 'Set MAIL_MAILER, MAIL_HOST and MAIL_FROM_ADDRESS in .env, then run php artisan config:clear. Email uses the application mail transport rather than credentials stored here.';
        }

        return 'Add the provider credentials under Configure.';
    }

    /**
     * Whether Laravel's configured mailer can actually reach an inbox.
     *
     * `log` and `array` are legitimate mailers and a correct local setup,
     * but they deliver to a file and to memory respectively. Reporting
     * them as configured would tell an operator that campaign email is
     * working when nothing leaves the building.
     */
    private function applicationMailerCanDeliver(): bool
    {
        $mailer = (string) config('mail.default');
        $settings = config("mail.mailers.{$mailer}", []);
        $transport = $settings['transport'] ?? $mailer;

        return match ($transport) {
            'smtp' => filled($settings['host'] ?? null)
                && filled(config('mail.from.address')),
            'log', 'array', 'null' => false,
            // ses, postmark, resend, mailgun and friends carry their own
            // credentials in config/services.php; presence of the mailer
            // is as much as can be checked here without calling out.
            default => true,
        };
    }
}
