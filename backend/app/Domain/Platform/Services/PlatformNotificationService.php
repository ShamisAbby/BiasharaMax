<?php

namespace App\Domain\Platform\Services;

use App\Domain\AiInsights\Services\InsightGenerationService;
use App\Domain\Licensing\Models\License;
use App\Domain\Monitoring\Models\BackupRecord;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Support\Models\SupportTicket;
use Illuminate\Support\Carbon;

/**
 * Computes a live "needs attention" feed from real signals that already
 * exist elsewhere in the platform (security alerts, failed backups,
 * expiring licences, at-risk businesses).
 *
 * Still derived rather than stored: every item is current state, not an
 * inbox entry, and it is recomputed on each call. Dismissal and
 * email-once tracking live alongside it in
 * PlatformNotificationStateService, keyed on each item's stable id —
 * because there is nothing here to mark read, only conditions that are
 * or are not still true.
 *
 * The consequence worth knowing: an item cannot be permanently silenced.
 * Dismiss a failed-backup alert and it stays hidden while that backup is
 * still failed; fix the backup and the alert stops existing; break it
 * again and it returns as new. "Dismiss" means "I have seen this", not
 * "never tell me again" — the wrong promise to make about something
 * that is still broken.
 */
class PlatformNotificationService
{
    public function __construct(
        private readonly InsightGenerationService $insights,
        private readonly PlatformNotificationStateService $state,
    ) {}

    /**
     * The feed as an operator should see it: everything currently true,
     * minus anything they have dismissed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function current(): array
    {
        $dismissed = $this->state->dismissedKeys();

        return collect($this->all())
            ->reject(fn (array $item): bool => in_array($item['id'], $dismissed, true))
            ->values()
            ->all();
    }

    /**
     * Every derived item, dismissals included.
     *
     * Separate from [current] because the two callers want different
     * things: the bell wants what is left to look at, while the emailer
     * and the state reconciler need the complete set — pruning state
     * against a dismissal-filtered list would delete the very rows
     * recording those dismissals, and every dismissed item would spring
     * back on the next poll.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $items = [];

        foreach (SecurityAlert::query()->where('is_resolved', false)->whereIn('severity', [SecurityAlert::SEVERITY_CRITICAL, SecurityAlert::SEVERITY_HIGH])->latest('created_at')->limit(5)->get() as $alert) {
            $items[] = [
                'id' => 'security-alert-'.$alert->id,
                'type' => 'security',
                'severity' => $alert->severity === SecurityAlert::SEVERITY_CRITICAL ? 'critical' : 'high',
                'title' => 'Unresolved '.$alert->severity.' security alert',
                'description' => $alert->description,
                'href' => route('platform.operations.security.index'),
                'created_at' => $alert->created_at,
            ];
        }

        foreach (BackupRecord::query()->where('status', BackupRecord::STATUS_FAILED)->latest('started_at')->limit(3)->get() as $backup) {
            $items[] = [
                'id' => 'backup-'.$backup->id,
                'type' => 'backup',
                'severity' => 'high',
                'title' => 'Backup failed',
                'description' => ucfirst($backup->type).' backup failed on '.$backup->started_at->format('M j, Y'),
                'href' => route('platform.system.backups.index'),
                'created_at' => $backup->started_at,
            ];
        }

        $expiringSoon = License::query()
            ->where('status', License::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [Carbon::now(), Carbon::now()->addDays(30)])
            ->latest('expires_at')
            ->limit(5)
            ->get();

        foreach ($expiringSoon as $license) {
            $items[] = [
                'id' => 'license-'.$license->id,
                'type' => 'license',
                'severity' => 'medium',
                'title' => 'License expiring soon',
                'description' => 'Expires '.$license->expires_at->diffForHumans(),
                'href' => route('platform.licenses.dashboard'),
                'created_at' => $license->expires_at,
            ];
        }

        /*
         * Support tickets waiting on us.
         *
         * Only statuses where the customer is the one waiting — resolved
         * and closed are excluded, and so is `in_progress`, which means
         * an agent already has it. An alert for work already being done
         * is noise, and noise is what stops the real ones being read.
         *
         * Severity follows the customer's own priority. They are the
         * only party who knows whether their business has stopped, and
         * second-guessing that here would quietly override it.
         */
        $awaitingReply = SupportTicket::query()
            ->whereIn('status', [
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_REOPENED,
            ])
            ->with('business')
            ->latest('created_at')
            ->limit(10)
            ->get();

        foreach ($awaitingReply as $ticket) {
            $items[] = [
                'id' => 'support-ticket-'.$ticket->id,
                'type' => 'support',
                'severity' => match ($ticket->priority) {
                    SupportTicket::PRIORITY_URGENT => 'critical',
                    SupportTicket::PRIORITY_HIGH => 'high',
                    default => 'medium',
                },
                'title' => $ticket->status === SupportTicket::STATUS_REOPENED
                    ? 'Reopened ticket: '.$ticket->subject
                    : 'New support ticket: '.$ticket->subject,
                'description' => ($ticket->business?->name ?? 'Unknown business')
                    .' · '.$ticket->ticket_number,
                'href' => route('platform.operations.support.index'),
                'created_at' => $ticket->created_at,
            ];
        }

        foreach (collect($this->insights->churnRisk(5))->where('risk_score', '>=', 60) as $risk) {
            $items[] = [
                'id' => 'churn-'.$risk['business_id'],
                'type' => 'business',
                'severity' => 'medium',
                'title' => $risk['business_name'].' is at risk',
                'description' => implode(', ', $risk['reasons']),
                'href' => route('platform.businesses.index'),
                'created_at' => Carbon::now(),
            ];
        }

        return collect($items)
            ->sortByDesc(fn ($item) => $item['created_at'])
            ->values()
            ->all();
    }
}
