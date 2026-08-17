<x-filament-widgets::widget>
    <div class="bos-kpi-grid">
        @foreach ($cards as $card)
            <div class="bos-kpi-card bos-kpi-card--{{ $card['color'] }}">
                <div class="bos-kpi-card__top">
                    <div class="bos-kpi-card__icon bos-bg-{{ $card['color'] }}">
                        <x-filament::icon :icon="$card['icon']" />
                    </div>

                    @if (! empty($card['trend']))
                        @php
                            $trend = $card['trend'];
                            $min = min($trend);
                            $max = max($trend);
                            $range = max($max - $min, 1);
                            $points = collect($trend)->values()->map(function ($v, $i) use ($trend, $min, $range) {
                                $x = count($trend) > 1 ? ($i / (count($trend) - 1)) * 100 : 0;
                                $y = 24 - (($v - $min) / $range) * 20 - 2;

                                return round($x, 1) . ',' . round($y, 1);
                            })->implode(' ');
                        @endphp
                        <div class="bos-kpi-card__spark">
                            <svg viewBox="0 0 100 24" preserveAspectRatio="none">
                                <polyline
                                    points="{{ $points }}"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="bos-kpi-card__spark-line"
                                />
                            </svg>
                        </div>
                    @endif
                </div>

                <p class="bos-kpi-card__label">{{ $card['label'] }}</p>
                <p class="bos-kpi-card__value">{{ $card['value'] }}</p>

                @if ($card['changePercent'] !== null)
                    <p class="bos-kpi-card__change {{ $card['changePercent'] >= 0 ? 'bos-kpi-card__change--up' : 'bos-kpi-card__change--down' }}">
                        {{ $card['changePercent'] >= 0 ? '+' : '' }}{{ $card['changePercent'] }}% vs yesterday
                    </p>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
