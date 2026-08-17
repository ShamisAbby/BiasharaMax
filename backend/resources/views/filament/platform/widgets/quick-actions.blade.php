<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Quick Actions</x-slot>

        <div class="bos-quick-actions">
            @foreach ($actions as $action)
                <a href="{{ $action['url'] }}" class="bos-quick-action">
                    <span class="bos-quick-action__icon bos-bg-{{ $action['color'] }}">
                        <x-filament::icon :icon="$action['icon']" />
                    </span>
                    <span class="bos-quick-action__label">{{ $action['label'] }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
