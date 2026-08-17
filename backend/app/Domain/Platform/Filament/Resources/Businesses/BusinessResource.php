<?php

namespace App\Domain\Platform\Filament\Resources\Businesses;

use App\Domain\Business\Models\Business;
use App\Domain\Platform\Filament\Resources\Businesses\Pages\ListBusinesses;
use App\Domain\Platform\Filament\Resources\Businesses\Tables\BusinessesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately no create/edit/view pages, no form() — this mirrors
 * App\Domain\Platform\Http\Controllers\BusinessManagementController
 * exactly: platform staff never create or freely edit a business (that
 * happens through tenant signup), they only view the list and act on it
 * via a handful of specific operations (suspend, activate, change
 * subscription, impersonate), each modeled as a table Action below rather
 * than a generic edit form. `make:filament-resource --view` generated
 * Create/Edit/View page classes and Business Form/Infolist schema files
 * that aren't referenced here or in getPages() — they're unreachable but
 * were left on disk (this environment couldn't delete them); safe to
 * delete by hand, or repurpose later if a real "view business" detail
 * page is wanted.
 */
class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Tenants';

    public static function table(Table $table): Table
    {
        return BusinessesTable::configure($table);
    }

    /**
     * No view/edit page exists (see class docblock), so the default
     * global-search-result URL resolution (which tries view then edit)
     * would return null — send matches to the index/list instead so
     * search results stay clickable.
     */
    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinesses::route('/'),
        ];
    }
}
