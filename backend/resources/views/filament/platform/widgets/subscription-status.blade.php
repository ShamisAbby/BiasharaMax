<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Subscription Status</x-slot>

        @forelse ($statuses as $status)
            <div class="bos-badge-list__row">
                <span class="bos-badge-list__label">{{ $status['label'] }}</span>
                <x-filament::badge :color="match (strtolower($status['label'])) {
                    'active' => 'success',
                    'trialing' => 'info',
                    'past_due' => 'warning',
                    'suspended', 'expired', 'canceled', 'cancelled' => 'danger',
                    default => 'gray',
                }">
                    {{ $status['count'] }}
                </x-filament::badge>
            </div>
        @empty
            <div class="bos-empty">
                <div class="bos-empty__icon">
                    <x-filament::icon icon="heroicon-o-credit-card" />
                </div>
                <p class="bos-empty__title">No subscriptions yet</p>
                <p class="bos-empty__description">Subscription status breakdown appears here once businesses subscribe.</p>
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
