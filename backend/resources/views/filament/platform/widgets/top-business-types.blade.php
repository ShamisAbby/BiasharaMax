<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Top Business Types</x-slot>

        @forelse ($types as $type)
            <div class="bos-bar-row">
                <div class="bos-bar-row__top">
                    <span class="bos-bar-row__label">{{ $type['label'] }}</span>
                    <span class="bos-bar-row__value">{{ $type['count'] }}</span>
                </div>
                <div class="bos-bar-track">
                    <div class="bos-bar-fill bos-bg-violet" style="width: {{ $type['percent'] }}%"></div>
                </div>
            </div>
        @empty
            <div class="bos-empty">
                <div class="bos-empty__icon">
                    <x-filament::icon icon="heroicon-o-building-office-2" />
                </div>
                <p class="bos-empty__title">No businesses yet</p>
                <p class="bos-empty__description">Type distribution appears once businesses register.</p>
                <div class="bos-empty__action">
                    <x-filament::button tag="a" :href="$businessesUrl" color="gray" size="sm">
                        View businesses
                    </x-filament::button>
                </div>
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
