<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <span class="bos-ai-heading">
                <x-filament::icon icon="heroicon-o-sparkles" />
                AI Recommendations
            </span>
        </x-slot>

        @if (! $aiConfigured)
            <div class="bos-row bos-wrap">
                <p class="bos-muted">
                    No AI provider is enabled, so narrative recommendations aren't available yet.
                </p>
                <x-filament::button
                    tag="a"
                    :href="\App\Domain\Platform\Filament\Resources\Integrations\IntegrationResource::getUrl()"
                    color="gray"
                    size="sm"
                >
                    Enable an AI integration
                </x-filament::button>
            </div>
        @elseif ($recommendations->isEmpty())
            <p class="bos-muted">
                No narrative insights generated yet.
                <a href="{{ \App\Domain\Platform\Filament\Pages\AiInsights::getUrl() }}" class="bos-link">
                    Generate one
                </a>
            </p>
        @else
            <div>
                @foreach ($recommendations as $insight)
                    <div class="bos-recommendation">
                        <p class="bos-recommendation__title">{{ $insight->title }}</p>
                        <p class="bos-recommendation__summary">{{ $insight->summary }}</p>
                        <p class="bos-recommendation__time">{{ $insight->created_at?->diffForHumans() }}</p>
                    </div>
                @endforeach
            </div>

            <a href="{{ \App\Domain\Platform\Filament\Pages\AiInsights::getUrl() }}" class="bos-link">
                View all insights →
            </a>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
