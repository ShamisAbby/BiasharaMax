<x-filament-widgets::widget>
    <div class="bos-header">
        <div>
            <h1 class="bos-header__greeting">
                {{ $greeting }}, {{ $firstName }} <span aria-hidden="true">👋</span>
            </h1>
            <p class="bos-header__subtitle">
                Welcome back to BiasharaMax. Here's what's happening across your platform today.
            </p>
            <p class="bos-header__meta">
                {{ $today->format('l, j F Y') }} · BiasharaMax v{{ $platformVersion }}
            </p>
        </div>

        <div class="bos-header__badges">
            <x-filament::badge :color="$healthColor">
                Platform {{ $healthLabel }}
            </x-filament::badge>
            <x-filament::badge :color="$databaseOnline ? 'success' : 'danger'">
                DB {{ $databaseOnline ? 'Online' : 'Offline' }}
            </x-filament::badge>
            {{-- Grey where Redis isn't configured: this deployment runs
                 cache, session and queue on the database, so neither
                 "Online" nor "Offline" describes it honestly. --}}
            <x-filament::badge :color="! $redisInUse ? 'gray' : ($redisOnline ? 'success' : 'danger')">
                Redis {{ ! $redisInUse ? 'Not in use' : ($redisOnline ? 'Online' : 'Offline') }}
            </x-filament::badge>
        </div>
    </div>
</x-filament-widgets::widget>
