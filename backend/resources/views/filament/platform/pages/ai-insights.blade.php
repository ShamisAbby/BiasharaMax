<x-filament-panels::page>
    @if (! $this->aiConfigured)
        <x-filament::section>
            <div class="bos-banner">
                <x-filament::icon icon="heroicon-o-information-circle" />
                No AI provider is currently enabled. Narrative summaries require an enabled Integration in the "AI" category.
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Generate narrative</x-slot>

        {{ $this->form }}

        <div style="margin-top: 1rem;">
            <x-filament::button wire:click="generateNarrative" icon="heroicon-o-sparkles">
                Generate
            </x-filament::button>
        </div>
    </x-filament::section>

    <div class="bos-grid-2">
        <x-filament::section>
            <x-slot name="heading">Revenue forecast</x-slot>

            @php($revenue = $this->revenueForecast)
            <p class="bos-pulse-stat__label">Next month forecast (linear trend)</p>
            <p class="bos-pulse-stat__value">{{ number_format($revenue['forecast_next_month'] ?? 0, 2) }}</p>
            <div style="margin-top: 0.75rem;">
                @foreach ($revenue['history'] ?? [] as $month => $value)
                    <div class="bos-kv-row">
                        <span class="bos-kv-row__label">{{ $month }}</span>
                        <span class="bos-kv-row__value">{{ number_format($value, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Subscription forecast</x-slot>

            @php($subs = $this->subscriptionForecast)
            <p class="bos-pulse-stat__label">Next month forecast (linear trend)</p>
            <p class="bos-pulse-stat__value">{{ number_format($subs['forecast_next_month'] ?? 0) }}</p>
            <div style="margin-top: 0.75rem;">
                @foreach ($subs['history'] ?? [] as $month => $value)
                    <div class="bos-kv-row">
                        <span class="bos-kv-row__label">{{ $month }}</span>
                        <span class="bos-kv-row__value">{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Growth trend</x-slot>

            @php($growth = $this->growthTrend['this_month'] ?? ['businesses' => 0, 'growth_percent' => 0])
            <p class="bos-pulse-stat__label">New businesses this month</p>
            <p class="bos-pulse-stat__value">{{ $growth['businesses'] }}</p>
            <p class="{{ $growth['growth_percent'] >= 0 ? 'bos-text-success' : 'bos-text-danger' }}">
                {{ $growth['growth_percent'] >= 0 ? '+' : '' }}{{ $growth['growth_percent'] }}% vs last month
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Churn risk</x-slot>

            @if (empty($this->churnRisk))
                <p class="bos-muted">No businesses currently at risk.</p>
            @else
                <div>
                    @foreach ($this->churnRisk as $row)
                        <div class="bos-badge-list__row">
                            <div>
                                <p class="bos-recommendation__title">{{ $row['business_name'] }}</p>
                                <p class="bos-recommendation__summary">{{ implode('; ', $row['reasons']) }}</p>
                            </div>
                            <x-filament::badge :color="$row['risk_score'] >= 60 ? 'danger' : 'warning'">
                                {{ $row['risk_score'] }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Most active businesses (30d)</x-slot>

            @forelse ($this->mostActiveBusinesses as $row)
                <div class="bos-kv-row">
                    <span class="bos-kv-row__label">{{ $row['name'] }}</span>
                    <span class="bos-kv-row__value">{{ $row['transaction_count'] }} txns</span>
                </div>
            @empty
                <p class="bos-muted">No data.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Inactive businesses</x-slot>

            @forelse ($this->inactiveBusinesses as $row)
                <div class="bos-kv-row">
                    <span class="bos-kv-row__label">{{ $row['name'] }}</span>
                    <span class="bos-kv-row__value">{{ $row['days_inactive'] }}d inactive</span>
                </div>
            @empty
                <p class="bos-muted">No data.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Revenue by business type</x-slot>

            @forelse ($this->revenueByBusinessType as $row)
                <div class="bos-kv-row">
                    <span class="bos-kv-row__label">{{ $row['business_type'] }}</span>
                    <span class="bos-kv-row__value">{{ number_format($row['total'], 2) }}</span>
                </div>
            @empty
                <p class="bos-muted">No data.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Revenue by country</x-slot>

            @forelse ($this->revenueByCountry as $row)
                <div class="bos-kv-row">
                    <span class="bos-kv-row__label">{{ $row['country'] }}</span>
                    <span class="bos-kv-row__value">{{ number_format($row['total'], 2) }}</span>
                </div>
            @empty
                <p class="bos-muted">No data.</p>
            @endforelse
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Saved insights</x-slot>

        @forelse ($this->savedInsights as $insight)
            <div class="bos-recommendation">
                <div class="bos-row bos-wrap">
                    <p class="bos-recommendation__title">{{ $insight->title }}</p>
                    <div class="bos-header__badges">
                        <x-filament::badge :color="$insight->is_read ? 'gray' : 'info'">
                            {{ $insight->is_read ? 'Read' : 'Unread' }}
                        </x-filament::badge>
                        <x-filament::badge color="gray">{{ $insight->generated_by }}</x-filament::badge>
                    </div>
                </div>
                <p class="bos-recommendation__summary">{{ $insight->summary }}</p>
                <p class="bos-recommendation__time">{{ $insight->created_at?->diffForHumans() }}</p>

                @unless ($insight->is_read)
                    <div style="margin-top: 0.5rem;">
                        <x-filament::button
                            size="xs"
                            color="gray"
                            wire:click="markInsightRead('{{ $insight->id }}')"
                        >
                            Mark read
                        </x-filament::button>
                    </div>
                @endunless
            </div>
        @empty
            <p class="bos-muted">No narrative insights generated yet.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
