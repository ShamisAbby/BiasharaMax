@php
    $systemInfo = $this->getSystemInfo();
    $queue = $this->getQueueStatus();
    $migrations = $this->getMigrationStatus();
    $routes = $this->getRoutes();
    $routeCount = $this->getRouteCount();
    $webhooks = $this->getWebhooks();
    $tokens = $this->getApiTokens();
    $canManage = static::canManage();
@endphp

<x-filament-panels::page>
    <div class="bos-grid-2">
        <x-filament::section>
            <x-slot name="heading">System Information</x-slot>

            @foreach ([
                'php_version' => 'PHP version',
                'laravel_version' => 'Laravel version',
                'environment' => 'Environment',
                'debug_mode' => 'Debug mode',
                'timezone' => 'Timezone',
                'database_driver' => 'Database driver',
                'cache_driver' => 'Cache driver',
                'queue_driver' => 'Queue driver',
                'session_driver' => 'Session driver',
            ] as $key => $label)
                <div class="bos-kv-row">
                    <span class="bos-kv-row__label">{{ $label }}</span>
                    <span class="bos-kv-row__value">
                        @if ($key === 'debug_mode')
                            {{-- Debug mode on in production leaks stack
                                 traces and config to anyone who triggers
                                 an error, so it is called out rather than
                                 printed as a neutral "true". --}}
                            <x-filament::badge
                                :color="$systemInfo['debug_mode'] && $systemInfo['environment'] === 'production' ? 'danger' : 'gray'"
                            >
                                {{ $systemInfo['debug_mode'] ? 'true' : 'false' }}
                            </x-filament::badge>
                        @else
                            {{ $systemInfo[$key] ?? '—' }}
                        @endif
                    </span>
                </div>
            @endforeach
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Queue &amp; Cache</x-slot>

            <div class="bos-kv-row">
                <span class="bos-kv-row__label">Connection</span>
                <span class="bos-kv-row__value">{{ $queue['connection'] ?? '—' }}</span>
            </div>
            <div class="bos-kv-row">
                <span class="bos-kv-row__label">Failed jobs</span>
                <span class="bos-kv-row__value {{ ($queue['failed_jobs'] ?? 0) > 0 ? 'bos-text-danger' : '' }}">
                    {{ number_format($queue['failed_jobs'] ?? 0) }}
                </span>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Migration Status</x-slot>

        <p>{{ number_format($migrations['ran']) }} / {{ number_format($migrations['total']) }} migrations ran.</p>

        @if ($migrations['pending'] === [])
            <p class="bos-text-success">All migrations are up to date.</p>
        @else
            <div class="bos-banner bos-banner--warning">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                {{ count($migrations['pending']) }} pending — run <code>php artisan migrate</code>.
            </div>
            @foreach ($migrations['pending'] as $pending)
                <div class="bos-kv-row">
                    <span class="bos-kv-row__label">{{ $pending }}</span>
                </div>
            @endforeach
        @endif
    </x-filament::section>

    @if ($this->plainTextToken)
        {{-- Shown once and only once. Sanctum stores a hash, so this
             string does not exist anywhere after the page is left —
             hence a banner that must be dismissed rather than a toast
             that disappears on its own. --}}
        <x-filament::section>
            <x-slot name="heading">Copy your new token now</x-slot>
            <x-slot name="description">This is the only time it will be shown. Leaving this page means issuing a new one.</x-slot>

            <div
                class="bos-banner bos-banner--warning"
                x-data="{ copied: false }"
            >
                <code style="flex: 1; word-break: break-all;">{{ $this->plainTextToken }}</code>

                <x-filament::button
                    size="sm"
                    color="gray"
                    x-on:click="
                        navigator.clipboard.writeText(@js($this->plainTextToken));
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                    "
                >
                    <span x-show="! copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied</span>
                </x-filament::button>

                <x-filament::button size="sm" color="gray" wire:click="dismissToken">
                    Done
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">API Tokens</x-slot>
        <x-slot name="description">Tokens belonging to your own account</x-slot>

        @if ($tokens->isEmpty())
            <div class="bos-empty">
                <p class="bos-empty__description">No API tokens yet.</p>
            </div>
        @else
            @foreach ($tokens as $token)
                <div class="bos-kv-row">
                    <span class="bos-kv-row__label">
                        {{ $token->name }}
                        <span class="bos-muted" style="display: block;">
                            Last used:
                            {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never' }}
                        </span>
                    </span>
                    <span class="bos-kv-row__value">
                        @if ($canManage)
                            {{-- Irreversible and instant: anything holding
                                 this token stops working the moment it is
                                 clicked, so it confirms and names itself. --}}
                            <x-filament::link
                                tag="button"
                                color="danger"
                                wire:click="revokeToken('{{ $token->id }}')"
                                wire:confirm="Revoke &quot;{{ $token->name }}&quot;? Anything using this token will stop working immediately."
                            >
                                Revoke
                            </x-filament::link>
                        @endif
                    </span>
                </div>
            @endforeach
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Webhooks</x-slot>

        @if ($webhooks->isEmpty())
            <div class="bos-empty">
                <p class="bos-empty__description">No webhooks yet.</p>
            </div>
        @else
            @foreach ($webhooks as $webhook)
                <div class="bos-kv-row">
                    <span class="bos-kv-row__label">
                        {{ $webhook->name }}
                        <span class="bos-muted" style="display: block;">
                            {{ $webhook->url }} &middot; {{ implode(', ', $webhook->events ?? []) }}
                        </span>
                    </span>
                    <span class="bos-kv-row__value">
                        <x-filament::badge :color="$webhook->is_active ? 'success' : 'gray'">
                            {{ $webhook->is_active ? 'Active' : 'Inactive' }}
                        </x-filament::badge>

                        <x-filament::link
                            tag="button"
                            wire:click="toggleDeliveries('{{ $webhook->id }}')"
                        >
                            Logs ({{ $webhook->deliveries_count }})
                        </x-filament::link>

                        @if ($canManage)
                            <x-filament::link
                                tag="button"
                                color="danger"
                                wire:click="deleteWebhook('{{ $webhook->id }}')"
                                wire:confirm="Delete webhook &quot;{{ $webhook->name }}&quot;? Its delivery history goes with it."
                            >
                                Delete
                            </x-filament::link>
                        @endif
                    </span>
                </div>

                @if ($this->expandedWebhookId === $webhook->id)
                    @php($deliveries = $this->getDeliveries())

                    @if ($deliveries->isEmpty())
                        <div class="bos-empty">
                            <p class="bos-empty__description">
                                No deliveries yet — nothing has fired
                                {{ implode(' or ', $webhook->events ?? []) ?: 'these events' }}.
                            </p>
                        </div>
                    @else
                        <div class="bos-table-wrapper">
                            <table class="bos-table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Status</th>
                                        <th>Attempt</th>
                                        <th>When</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($deliveries as $delivery)
                                        <tr>
                                            <td><code>{{ $delivery->event }}</code></td>
                                            <td>
                                                <x-filament::badge :color="$delivery->is_successful ? 'success' : 'danger'">
                                                    {{ $delivery->response_status ?? 'no response' }}
                                                </x-filament::badge>
                                            </td>
                                            <td class="bos-muted">{{ $delivery->attempt }}</td>
                                            <td>{{ ($delivery->delivered_at ?? $delivery->created_at)?->format('d/m/Y, H:i:s') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            @endforeach
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Route List</x-slot>
        <x-slot name="description">
            {{ number_format($routeCount) }} registered routes
            @if (count($routes) < $routeCount)
                &middot; showing {{ number_format(count($routes)) }}
            @endif
        </x-slot>

        <x-filament::input.wrapper>
            <x-filament::input
                type="text"
                wire:model.live.debounce.300ms="routeFilter"
                placeholder="Filter by URI or name"
            />
        </x-filament::input.wrapper>

        @if ($routes === [])
            <div class="bos-empty">
                <p class="bos-empty__description">No routes match that filter.</p>
            </div>
        @else
            <div class="bos-table-wrapper">
                <table class="bos-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>URI</th>
                            <th>Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($routes as $route)
                            <tr>
                                <td class="bos-muted">{{ $route['method'] }}</td>
                                <td><code>{{ $route['uri'] }}</code></td>
                                <td class="bos-muted">{{ $route['name'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
