<?php

namespace App\Domain\Platform\Filament\Pages;

use App\Domain\Finance\Services\FinanceReportService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Custom page, not a Resource — reports are computed from
 * App\Domain\Finance\Services\FinanceReportService::generate(), not
 * backed by an Eloquent model. Mirrors
 * App\Domain\Platform\Http\Controllers\FinanceReportController exactly:
 * same catalog() call for the report picker (including the "Sales"
 * category's `available: false` entries, disabled here rather than
 * hidden, with the same real reason shown), same date_from/date_to
 * filters. CSV/PDF export reuses the existing
 * platform.finance.reports.export.csv/pdf routes directly rather than
 * re-implementing file generation here.
 */
class FinanceReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $title = 'Finance Reports';

    protected string $view = 'filament.platform.pages.finance-reports';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'report' => 'revenue',
            'date_from' => null,
            'date_to' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filters')
                    ->columns(3)
                    ->schema([
                        Select::make('report')
                            ->options($this->reportOptions())
                            ->disableOptionWhen(fn (string $value): bool => in_array($value, $this->unavailableReportKeys(), true))
                            ->live()
                            ->required(),
                        DatePicker::make('date_from')->native(false)->live(),
                        DatePicker::make('date_to')->native(false)->live(),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<string, string>
     */
    protected function reportOptions(): array
    {
        return collect(app(FinanceReportService::class)->catalog())
            ->groupBy('category')
            ->map(fn ($reports) => $reports->pluck('label', 'key'))
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    protected function unavailableReportKeys(): array
    {
        return collect(app(FinanceReportService::class)->catalog())
            ->reject(fn (array $report) => $report['available'])
            ->pluck('key')
            ->all();
    }

    /**
     * @return array{columns: array<int, string>, rows: array<int, array<int, mixed>>, summary: array<string, mixed>|null}
     */
    public function getReportDataProperty(): array
    {
        $key = $this->data['report'] ?? 'revenue';

        return app(FinanceReportService::class)->generate($key, [
            'date_from' => $this->data['date_from'] ?? null,
            'date_to' => $this->data['date_to'] ?? null,
        ]);
    }

    public function getExportQuery(): array
    {
        return array_filter([
            'report' => $this->data['report'] ?? 'revenue',
            'date_from' => $this->data['date_from'] ?? null,
            'date_to' => $this->data['date_to'] ?? null,
        ]);
    }
}
