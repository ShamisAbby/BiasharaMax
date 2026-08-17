<?php

namespace App\Domain\Platform\Filament\Resources\Subscribers\Pages;

use App\Domain\Platform\Filament\Resources\Subscribers\SubscriberResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscribers extends ListRecords
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
