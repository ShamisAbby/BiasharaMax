<?php

namespace App\Domain\Platform\Filament\Resources\SupportTickets;

use App\Domain\Platform\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Domain\Platform\Filament\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Domain\Support\Models\SupportTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Support Tickets';

    protected static ?string $recordTitleAttribute = 'subject';

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
        ];
    }
}
