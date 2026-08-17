<?php

namespace App\Domain\Platform\Filament\Resources\RegistrationCodes;

use App\Domain\Platform\Filament\Resources\RegistrationCodes\Pages\ListRegistrationCodes;
use App\Domain\Platform\Filament\Resources\RegistrationCodes\Tables\RegistrationCodesTable;
use App\Domain\Subscription\Models\RegistrationCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * No dedicated create/edit pages — codes are generated in bulk via the
 * table's "Generate codes" header action (RegistrationCodesTable), and
 * are never edited after creation, matching
 * App\Domain\Platform\Http\Controllers\RegistrationCodeController exactly
 * (only index/store/destroy routes exist, no update).
 */
class RegistrationCodeResource extends Resource
{
    protected static ?string $model = RegistrationCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?string $navigationLabel = 'Registration Codes';

    protected static ?string $recordTitleAttribute = 'code';

    public static function table(Table $table): Table
    {
        return RegistrationCodesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrationCodes::route('/'),
        ];
    }
}
