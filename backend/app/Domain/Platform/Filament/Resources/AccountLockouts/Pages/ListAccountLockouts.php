<?php

namespace App\Domain\Platform\Filament\Resources\AccountLockouts\Pages;

use App\Domain\Platform\Filament\Resources\AccountLockouts\AccountLockoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccountLockouts extends ListRecords
{
    protected static string $resource = AccountLockoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Lock account'),
        ];
    }
}
