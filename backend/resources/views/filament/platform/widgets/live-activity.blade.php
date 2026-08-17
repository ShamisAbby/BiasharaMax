<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Live Activity</x-slot>
        <x-slot name="description">Latest actions across the platform</x-slot>

        @forelse ($activity as $entry)
            <div class="bos-activity-item">
                <div>
                    <p class="bos-activity-item__text">
                        <strong>{{ $entry['actor_name'] }}</strong>
                        {{ str_replace('_', ' ', $entry['action']) }}
                        @if ($entry['auditable_type'])
                            {{ str($entry['auditable_type'])->headline() }}
                        @endif
                    </p>
                    <p class="bos-activity-item__meta">
                        {{ $entry['module'] }}
                        @if ($entry['business_name'])
                            · {{ $entry['business_name'] }}
                        @endif
                    </p>
                </div>
                <span class="bos-activity-item__time">{{ \Illuminate\Support\Carbon::parse($entry['created_at'])->diffForHumans() }}</span>
            </div>
        @empty
            <div class="bos-empty">
                <div class="bos-empty__icon">
                    <x-filament::icon icon="heroicon-o-clock" />
                </div>
                <p class="bos-empty__title">No activity yet</p>
                <p class="bos-empty__description">Actions across the platform will show up here in real time.</p>
            </div>
        @endforelse

        <a href="{{ \App\Domain\Platform\Filament\Resources\AuditLogs\AuditLogResource::getUrl() }}" class="bos-link">
            View all activity →
        </a>
    </x-filament::section>
</x-filament-widgets::widget>
