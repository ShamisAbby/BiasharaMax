<?php

/**
 * Where platform operator alerts are emailed.
 *
 * Config rather than a hardcoded address in a notification class, for
 * the ordinary reason: the person who receives these will change, and
 * when they do it should be a deploy, not a patch. It also keeps the
 * address out of version control if you set it in `.env`.
 *
 * Scope is deliberately limited to **platform** events — security
 * alerts, failed backups, expiring licences, at-risk businesses. Tenant
 * notifications (low stock, sale returns, online orders) are not
 * forwarded here. Those belong to the business that owns them, and
 * routing every tenant's operational data to a single operator inbox
 * would be both unmanageable in volume and a poor default for customer
 * data.
 */
return [

    /*
     * Recipients. Comma-separated in env for more than one:
     *
     *   PLATFORM_ALERT_RECIPIENTS="ops@example.com,oncall@example.com"
     *
     * Empty disables alert email entirely without removing the wiring —
     * the dispatch command still records what it would have sent, so
     * turning it back on does not produce a backlog flood.
     */
    'recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PLATFORM_ALERT_RECIPIENTS', 'shamisaziz52@gmail.com')),
    ))),

    /*
     * Severities worth an email.
     *
     * The feed also carries `medium` items — licences expiring within 30
     * days, businesses drifting toward churn. Those are worth a badge in
     * the top bar and not worth waking someone up, so by default only
     * critical and high are sent. Widen this if the inbox turns out to
     * be quieter than expected; the cost of getting it wrong in this
     * direction is a missed alert, and in the other direction it is an
     * inbox nobody reads, which causes missed alerts too.
     */
    'email_severities' => ['critical', 'high'],

    /*
     * Safety valve for the "one email per alert, immediately" rule.
     *
     * A misconfiguration that suddenly generates hundreds of items — a
     * backup loop, a security rule firing on every request — would
     * otherwise send hundreds of emails before anyone noticed. Above
     * this count in a single run the command sends one summary instead
     * and says so.
     */
    'max_individual_emails_per_run' => 10,

];
