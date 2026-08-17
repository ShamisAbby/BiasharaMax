<?php

namespace App\Domain\Platform\Filament\Resources\Licenses;

use App\Domain\Platform\Filament\Resources\Licenses\Pages\ListLicenses;
use App\Domain\Platform\Filament\Resources\Licenses\Tables\LicensesTable;
use App\Domain\Licensing\Models\License;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * No edit page — App\Domain\Platform\Http\Controllers\LicenseController
 * has no update route either, only the lifecycle actions (renew,
 * suspend, restore, revoke, reset-activation) exposed as table row
 * actions in LicensesTable, matching the old UI's Show page actions.
 * Device management (per-device deactivate) and the analytics dashboard
 * are not yet ported — flagged as a follow-up, not silently dropped.
 */
class LicenseResource extends Resource
{
    protected static ?string $model = License::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?string $recordTitleAttribute = 'license_key';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('activeDevices');
    }

    public static function table(Table $table): Table
    {
        return LicensesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicenses::route('/'),
        ];
    }
}
