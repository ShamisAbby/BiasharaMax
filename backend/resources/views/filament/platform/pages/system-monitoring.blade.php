@php
    $live = $this->getLive();
    $trend = $this->getTrend();
    $backups = $this->getBackups();
@endphp

<x-filament-panels::page>
    <p class="bos-muted">
        {{ $live['uptime'] ?? '—' }}
        @if (! empty($live['platform_version']))
            &middot; v{{ $live['platform_version'] }}
        @endif
    </p>

    {{-- Headline gauges. Health uses the inverse colour scale to the
         three usage figures, which is why it asks a different helper. --}}
    <div class="bos-kpi-grid">
        @foreach ([
            ['label' => 'CPU Usage', 'value' => $live['cpu_usage'] ?? null, 'icon' => 'heroicon-o-cpu-chip', 'tone' => $this->usageTone($live['cpu_usage'] ?? null)],
            ['label' => 'Memory Usage', 'value' => $live['memory_usage'] ?? null, 'icon' => 'heroicon-o-circle-stack', 'tone' => $this->usageTone($live['memory_usage'] ?? null)],
            ['label' => 'Disk Usage', 'value' => $live['disk_usage'] ?? null, 'icon' => 'heroicon-o-server', 'tone' => $this->usageTone($live['disk_usage'] ?? null)],
            ['label' => 'Health Score', 'value' => $live['health_score'] ?? null, 'icon' => 'heroicon-o-shield-check', 'tone' => $this->healthTone($live['health_score'] ?? null)],
        ] as $card)
            <div class="bos-kpi-card bos-kpi-card--{{ $card['tone'] }}">
                <div class="bos-kpi-card__top">
                    <span class="bos-kpi-card__icon bos-bg-{{ $card['tone'] }}">
                        <x-filament::icon :icon="$card['icon']" />
                    </span>
                </div>
                <p class="bos-kpi-card__label">{{ $card['label'] }}</p>
                <p class="bos-kpi-card__value">
                    {{ $card['value'] === null ? '—' : round($card['value']).'%' }}
                </p>

                @if ($card['value'] !== null)
                    <div class="bos-bar-track">
                        <div
                            class="bos-bar-fill bos-bg-{{ $card['tone'] }}"
                            style="width: {{ max(0, min(100, (float) $card['value'])) }}%"
                        ></div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="bos-kpi-grid">
        <x-filament::section>
            <x-slot name="heading">Database</x-slot>
            <p class="bos-pulse-stat__value">
                {{ $live['db_response_time_ms'] === null ? '—' : number_format($live['db_response_time_ms'], 2).' ms' }}
            </p>
            <p class="bos-pulse-stat__label">Response time</p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Redis</x-slot>
            <x-filament::badge :color="($live['redis_status'] ?? null) === 'online' ? 'success' : 'danger'">
                {{ $live['redis_status'] ?? 'unknown' }}
            </x-filament::badge>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Horizon / Queue Workers</x-slot>
            {{-- "stopped" is warning rather than danger on purpose: queued
                 work is still accumulating and nothing is lost, so this is
                 a "go start the worker" state, not an outage. --}}
            <x-filament::badge :color="($live['horizon_status'] ?? null) === 'running' ? 'success' : 'warning'">
                {{ $live['horizon_status'] ?? 'unknown' }}
            </x-filament::badge>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Queue</x-slot>
            <p>
                {{ number_format($live['queue_pending'] ?? 0) }} pending
                &middot;
                <span class="{{ ($live['queue_failed'] ?? 0) > 0 ? 'bos-text-danger' : '' }}">
                    {{ number_format($live['queue_failed'] ?? 0) }} failed
                </span>
            </p>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">24-Hour Trend</x-slot>

        @if ($trend->isEmpty())
            <div class="bos-empty">
                <p class="bos-empty__description">
                    No historical data yet — snapshots are recorded every 5 minutes.
                </p>
            </div>
        @else
            <div class="bos-table-wrapper">
                <table class="bos-table">
                    <thead>
                        <tr>
                            <th>Recorded</th>
                            <th>CPU</th>
                            <th>Memory</th>
                            <th>Disk</th>
                            <th>Health</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trend as $point)
                            <tr>
                                <td>{{ $point->recorded_at?->format('H:i') }}</td>
                                <td>{{ round($point->cpu_usage) }}%</td>
                                <td>{{ round($point->memory_usage) }}%</td>
                                <td>{{ round($point->disk_usage) }}%</td>
                                <td>{{ round($point->health_score) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Backup Status</x-slot>

        @if ($backups->isEmpty())
            <div class="bos-empty">
                <p class="bos-empty__description">No backups recorded yet.</p>
            </div>
        @else
            <div class="bos-table-wrapper">
                <table class="bos-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Started</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $backup)
                            <tr>
                                <td>{{ $backup->type }}</td>
                                <td>
                                    <x-filament::badge
                                        :color="match ($backup->status) {
                                            'success', 'completed' => 'success',
                                            'failed' => 'danger',
                                            default => 'warning',
                                        }"
                                    >
                                        {{ $backup->status }}
                                    </x-filament::badge>
                                </td>
                                <td>{{ $backup->started_at?->format('d/m/Y, H:i:s') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
