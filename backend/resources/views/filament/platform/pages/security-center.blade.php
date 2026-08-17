@php
    $summary = $this->getSummary();
    $alerts = $this->getAlerts();
    $blockedIps = $this->getBlockedIps();
    $lockouts = $this->getLockouts();
    $failedLogins = $this->getFailedLogins();
    $activeSessions = $this->getActiveSessions();
    $canManage = static::canManage();
@endphp

<x-filament-panels::page>
    <div class="bos-kpi-grid">
        @foreach ([
            ['label' => 'Failed Logins (24h)', 'value' => $summary['failed_logins_24h'], 'icon' => 'heroicon-o-lock-closed', 'tone' => $summary['failed_logins_24h'] > 0 ? 'red' : 'gray'],
            ['label' => 'Blocked IPs', 'value' => $summary['blocked_ips_count'], 'icon' => 'heroicon-o-no-symbol', 'tone' => 'slate'],
            ['label' => 'Active Lockouts', 'value' => $summary['active_lockouts_count'], 'icon' => 'heroicon-o-lock-closed', 'tone' => $summary['active_lockouts_count'] > 0 ? 'amber' : 'gray'],
            ['label' => 'Unresolved Alerts', 'value' => $summary['unresolved_alerts_count'], 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => $summary['unresolved_alerts_count'] > 0 ? 'orange' : 'gray'],
            ['label' => 'Active Sessions', 'value' => $summary['active_sessions_count'], 'icon' => 'heroicon-o-user-group', 'tone' => 'emerald'],
        ] as $card)
            <div class="bos-kpi-card bos-kpi-card--{{ $card['tone'] }}">
                <div class="bos-kpi-card__top">
                    <span class="bos-kpi-card__icon bos-bg-{{ $card['tone'] }}">
                        <x-filament::icon :icon="$card['icon']" />
                    </span>
                </div>
                <p class="bos-kpi-card__label">{{ $card['label'] }}</p>
                <p class="bos-kpi-card__value">{{ number_format($card['value']) }}</p>
            </div>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">Security Alerts</x-slot>

        @if ($alerts->isEmpty())
            <div class="bos-empty">
                <p class="bos-empty__description">No security alerts.</p>
            </div>
        @else
            @foreach ($alerts as $alert)
                <div class="bos-activity-item">
                    <div class="bos-activity-item__text">
                        {{ $alert->description }}
                        <div class="bos-activity-item__meta">
                            {{ $alert->created_at?->format('d/m/Y, H:i:s') }}
                        </div>
                    </div>

                    <div class="bos-activity-item__time">
                        <x-filament::badge
                            :color="match ($alert->severity) {
                                'critical', 'high' => 'danger',
                                'medium' => 'warning',
                                default => 'gray',
                            }"
                        >
                            {{ $alert->severity }}
                        </x-filament::badge>

                        @if ($alert->is_resolved)
                            <span class="bos-muted">resolved</span>
                        @elseif ($canManage)
                            {{-- Confirmation required: resolving is a
                                 single click next to a dense list, and an
                                 alert dismissed by mistake leaves no trace
                                 that anyone still needs to look at it. --}}
                            <x-filament::link
                                tag="button"
                                wire:click="resolveAlert('{{ $alert->id }}')"
                                wire:confirm="Mark this alert as resolved?"
                            >
                                Resolve
                            </x-filament::link>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </x-filament::section>

    <div class="bos-grid-2">
        <x-filament::section>
            <x-slot name="heading">Blocked IPs</x-slot>

            @if ($blockedIps->isEmpty())
                <div class="bos-empty">
                    <p class="bos-empty__description">No blocked IPs.</p>
                </div>
            @else
                @foreach ($blockedIps as $ip)
                    <div class="bos-kv-row">
                        <span class="bos-kv-row__label">
                            {{ $ip->ip_address }}
                            @if ($ip->reason)
                                <span class="bos-muted">&middot; {{ $ip->reason }}</span>
                            @endif
                        </span>
                        <span class="bos-kv-row__value">
                            <x-filament::badge :color="$ip->isActive() ? 'danger' : 'gray'">
                                {{ $ip->is_permanent ? 'permanent' : ($ip->isActive() ? 'temporary' : 'expired') }}
                            </x-filament::badge>
                            @if ($canManage)
                                <x-filament::link
                                    tag="button"
                                    wire:click="unblockIp('{{ $ip->id }}')"
                                    wire:confirm="Unblock {{ $ip->ip_address }}?"
                                >
                                    Unblock
                                </x-filament::link>
                            @endif
                        </span>
                    </div>
                @endforeach
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Account Lockouts</x-slot>

            @if ($lockouts->isEmpty())
                <div class="bos-empty">
                    <p class="bos-empty__description">No lockouts.</p>
                </div>
            @else
                @foreach ($lockouts as $lockout)
                    <div class="bos-kv-row">
                        <span class="bos-kv-row__label">
                            {{ class_basename($lockout->lockable_type) }}
                            <span class="bos-muted">&middot; {{ $lockout->reason ?: 'no reason recorded' }}</span>
                        </span>
                        <span class="bos-kv-row__value">
                            <x-filament::badge :color="$lockout->isActive() ? 'warning' : 'gray'">
                                {{ $lockout->isActive() ? 'locked' : 'released' }}
                            </x-filament::badge>
                            @if ($canManage && $lockout->isActive())
                                <x-filament::link
                                    tag="button"
                                    wire:click="unlockAccount('{{ $lockout->id }}')"
                                    wire:confirm="Unlock this account?"
                                >
                                    Unlock
                                </x-filament::link>
                            @endif
                        </span>
                    </div>
                @endforeach
            @endif
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Failed Login Attempts</x-slot>

        @if ($failedLogins->isEmpty())
            <div class="bos-empty">
                <p class="bos-empty__description">No failed login attempts.</p>
            </div>
        @else
            <div class="bos-table-wrapper">
                <table class="bos-table">
                    <thead>
                        <tr>
                            <th>Identifier</th>
                            <th>Guard</th>
                            <th>IP</th>
                            <th>Reason</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($failedLogins as $attempt)
                            <tr>
                                <td>{{ $attempt->email }}</td>
                                <td class="bos-muted">{{ $attempt->guard ?? '—' }}</td>
                                <td>{{ $attempt->ip_address }}</td>
                                <td class="bos-muted">{{ $attempt->reason ?? '—' }}</td>
                                <td>{{ $attempt->created_at?->format('d/m/Y, H:i:s') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Active Sessions</x-slot>
        <x-slot name="description">Active in the last 30 minutes</x-slot>

        @if (! $this->sessionsAreTracked())
            {{-- Worth saying out loud rather than showing an empty list.
                 Sessions are read from the `sessions` table, so a Redis
                 or file driver reports nobody online even while admins
                 are signed in — which reads as a bug, not a setting. --}}
            <div class="bos-banner bos-banner--warning">
                <x-filament::icon icon="heroicon-o-information-circle" />
                Session tracking needs <code>SESSION_DRIVER=database</code>.
                This installation uses <strong>{{ config('session.driver') }}</strong>,
                so active sessions cannot be listed here.
            </div>
        @elseif ($activeSessions->isEmpty())
            <div class="bos-empty">
                <p class="bos-empty__description">No active sessions.</p>
            </div>
        @else
            <div class="bos-table-wrapper">
                <table class="bos-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>IP</th>
                            <th>Last activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activeSessions as $session)
                            <tr>
                                <td>{{ $session['user_name'] ?? 'Guest' }}</td>
                                <td class="bos-muted">{{ $session['user_type'] ?? '—' }}</td>
                                <td>{{ $session['ip_address'] }}</td>
                                <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($session['last_activity'])->format('d/m/Y, H:i:s') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
