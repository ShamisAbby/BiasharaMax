<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Country Distribution</x-slot>

        @forelse ($countries as $country)
            <div class="bos-bar-row">
                <div class="bos-bar-row__top">
                    <span class="bos-bar-row__label">{{ $country['label'] }}</span>
                    <span class="bos-bar-row__value">{{ $country['count'] }}</span>
                </div>
                <div class="bos-bar-track">
                    <div class="bos-bar-fill bos-bg-blue" style="width: {{ $country['percent'] }}%"></div>
                </div>
            </div>
        @empty
            <div class="bos-empty">
                <div class="bos-empty__icon">
                    <x-filament::icon icon="heroicon-o-users" />
                </div>
                <p class="bos-empty__title">No businesses yet</p>
                <p class="bos-empty__description">Country distribution appears once businesses register.</p>
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
