<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Payment Methods</x-slot>

        @forelse ($methods as $method)
            <div class="bos-bar-row">
                <div class="bos-bar-row__top">
                    <span class="bos-bar-row__label">{{ $method['label'] }}</span>
                    <span class="bos-bar-row__value">{{ number_format($method['total'], 2) }}</span>
                </div>
                <div class="bos-bar-track">
                    <div class="bos-bar-fill bos-bg-indigo" style="width: {{ $method['percent'] }}%"></div>
                </div>
            </div>
        @empty
            <div class="bos-empty">
                <div class="bos-empty__icon">
                    <x-filament::icon icon="heroicon-o-credit-card" />
                </div>
                <p class="bos-empty__title">No payments yet</p>
                <p class="bos-empty__description">Payment method breakdown appears once transactions exist.</p>
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
