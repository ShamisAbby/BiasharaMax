<?php

namespace App\Domain\Platform\Filament\Resources\SupportTickets\Pages;

use App\Domain\Platform\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\ListRecords;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
