<?php

namespace Database\Seeders;

use App\Domain\Notifications\Models\NotificationChannel;
use App\Domain\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * Starter notification templates.
 *
 * Each one corresponds to something this platform already does — trials
 * ending, payments failing, licences expiring, stock running out, support
 * replying — rather than generic filler. The point of shipping them is
 * that an operator editing wording is a far smaller task than an operator
 * inventing it from an empty screen.
 *
 * **Placeholders are a convention, not a feature.** Nothing in the
 * codebase substitutes `{{ business_name }}` today; these read as
 * documentation of what each message should mention, and become live the
 * day a renderer is added. They are written in the `{{ snake_case }}`
 * style Blade and most template engines expect, so that renderer has an
 * obvious shape.
 *
 * `is_system` marks the ones tied to an automated event. It exists so a
 * future "delete template" action can refuse to remove something the
 * platform sends automatically — a deleted trial-ending template would
 * mean trials quietly ending with no warning.
 */
class NotificationTemplateSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const TEMPLATES = [
        [
            'slug' => 'trial-ending-soon',
            'name' => 'Trial ending soon',
            'channel' => NotificationChannel::CHANNEL_EMAIL,
            'category' => NotificationTemplate::CATEGORY_SUBSCRIPTION_REMINDER,
            'subject' => 'Your BiasharaMax trial ends in {{ days_remaining }} days',
            'body' => <<<'TEXT'
            Hello {{ owner_name }},

            Your free trial for {{ business_name }} ends on {{ trial_ends_at }}.

            To keep selling without interruption, choose a plan before then.
            Everything you have entered — products, sales, customers — stays
            exactly as it is.

            Choose a plan: {{ subscription_url }}

            If you have questions, reply to this email or open a support
            ticket from your dashboard.
            TEXT,
            'is_system' => true,
        ],

        [
            'slug' => 'payment-failed',
            'name' => 'Payment failed',
            'channel' => NotificationChannel::CHANNEL_EMAIL,
            'category' => NotificationTemplate::CATEGORY_PAYMENT_FAILURE,
            'subject' => 'We could not process your payment for {{ business_name }}',
            'body' => <<<'TEXT'
            Hello {{ owner_name }},

            We tried to charge {{ amount }} for your {{ plan_name }} subscription
            on {{ attempted_at }}, and the payment did not go through.

            Reason given by the provider: {{ failure_reason }}

            Your account is still active. We will try again on
            {{ next_attempt_at }}, or you can update your payment details now:

            {{ billing_url }}
            TEXT,
            'is_system' => true,
        ],

        [
            'slug' => 'payment-received',
            'name' => 'Payment received',
            'channel' => NotificationChannel::CHANNEL_EMAIL,
            'category' => NotificationTemplate::CATEGORY_PAYMENT_SUCCESS,
            'subject' => 'Payment received — {{ business_name }}',
            'body' => <<<'TEXT'
            Hello {{ owner_name }},

            We have received your payment of {{ amount }} for the
            {{ plan_name }} plan.

            Your subscription is active until {{ period_ends_at }}.

            Receipt: {{ receipt_url }}

            Thank you for using BiasharaMax.
            TEXT,
            'is_system' => true,
        ],

        [
            'slug' => 'licence-expiring',
            'name' => 'Desktop licence expiring',
            'channel' => NotificationChannel::CHANNEL_EMAIL,
            'category' => NotificationTemplate::CATEGORY_LICENSE_EXPIRY,
            'subject' => 'Your desktop licence expires on {{ expires_at }}',
            'body' => <<<'TEXT'
            Hello {{ owner_name }},

            The BiasharaMax Desktop licence for {{ business_name }}
            ({{ licence_key }}) expires on {{ expires_at }}.

            When it expires, tills using it will stop accepting new sales.
            Anything already recorded offline will still sync.

            Renew: {{ renewal_url }}
            TEXT,
            'is_system' => true,
        ],

        [
            'slug' => 'low-stock-alert',
            'name' => 'Low stock alert',
            // In-app rather than email: this fires often and is
            // operational, so it belongs where someone is already
            // working rather than in an inbox they will start ignoring.
            'channel' => NotificationChannel::CHANNEL_IN_APP,
            'category' => NotificationTemplate::CATEGORY_LOW_STOCK,
            'subject' => '{{ product_count }} products are running low',
            'body' => <<<'TEXT'
            {{ product_count }} products at {{ warehouse_name }} have fallen to
            or below their reorder level.

            Lowest: {{ lowest_product_name }} — {{ lowest_quantity }} left.

            Review and raise a purchase order: {{ inventory_url }}
            TEXT,
            'is_system' => true,
        ],

        [
            'slug' => 'support-ticket-replied',
            'name' => 'Support replied to your ticket',
            'channel' => NotificationChannel::CHANNEL_IN_APP,
            'category' => NotificationTemplate::CATEGORY_SUPPORT_TICKET_UPDATE,
            'subject' => 'Support replied to {{ ticket_number }}',
            'body' => <<<'TEXT'
            The BiasharaMax team has replied to your ticket
            "{{ ticket_subject }}".

            Read the reply and respond: {{ ticket_url }}
            TEXT,
            'is_system' => true,
        ],

        [
            'slug' => 'scheduled-maintenance',
            'name' => 'Scheduled maintenance',
            'channel' => NotificationChannel::CHANNEL_IN_APP,
            'category' => NotificationTemplate::CATEGORY_BROADCAST,
            'subject' => 'Scheduled maintenance on {{ maintenance_date }}',
            'body' => <<<'TEXT'
            BiasharaMax will be unavailable for about {{ duration }} on
            {{ maintenance_date }} from {{ start_time }}.

            Your desktop till keeps working offline during this window and
            will sync automatically once we are back.

            We are sorry for the disruption.
            TEXT,
            // Not a system template: this one is written and sent by hand
            // for each occasion, so an operator should be free to delete
            // or rewrite it entirely.
            'is_system' => false,
        ],

        [
            'slug' => 'welcome-new-business',
            'name' => 'Welcome, new business',
            'channel' => NotificationChannel::CHANNEL_EMAIL,
            'category' => NotificationTemplate::CATEGORY_USER_REGISTRATION,
            'subject' => 'Welcome to BiasharaMax, {{ business_name }}',
            'body' => <<<'TEXT'
            Hello {{ owner_name }},

            {{ business_name }} is set up and ready to use.

            Three things worth doing first:

            1. Add your products, or import them from a spreadsheet.
            2. Invite your team and give each person a role.
            3. Install the desktop till if you sell at a counter.

            Open your dashboard: {{ dashboard_url }}

            Your free trial runs until {{ trial_ends_at }}.
            TEXT,
            'is_system' => true,
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $template) {
            /*
             * `firstOrCreate`, not `updateOrCreate`.
             *
             * These are starting points meant to be edited. Re-running
             * the seeder after an operator has reworded a template must
             * not overwrite their copy — the wording is the whole value
             * of the row, and silently reverting it is the one behaviour
             * that would make people distrust seeding entirely.
             */
            NotificationTemplate::query()->firstOrCreate(
                ['slug' => $template['slug']],
                $template,
            );
        }
    }
}
