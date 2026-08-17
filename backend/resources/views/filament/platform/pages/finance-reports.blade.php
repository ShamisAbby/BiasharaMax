<x-filament-panels::page>
    {{ $this->form }}

    @php($report = $this->reportData)

    <x-filament::section>
        <x-slot name="heading">Results</x-slot>

        <div class="bos-toolbar">
            <x-filament::button
                tag="a"
                :href="route('platform.finance.reports.export.csv', $this->getExportQuery())"
                color="gray"
                icon="heroicon-o-arrow-down-tray"
            >
                Export CSV
            </x-filament::button>

            <x-filament::button
                tag="a"
                :href="route('platform.finance.reports.export.pdf', $this->getExportQuery())"
                color="gray"
                icon="heroicon-o-arrow-down-tray"
            >
                Export PDF
            </x-filament::button>
        </div>

        @if (! empty($report['rows']))
            <div class="bos-table-wrapper">
                <table class="bos-table">
                    <thead>
                        <tr>
                            @foreach ($report['columns'] as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (! empty($report['summary']))
                <div class="bos-summary-row">
                    @foreach ($report['summary'] as $label => $value)
                        <div>
                            <p class="bos-summary-row__label">{{ str($label)->headline() }}</p>
                            <p class="bos-summary-row__value">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <p class="bos-muted">No data for this report and date range.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
