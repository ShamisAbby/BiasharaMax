<?php

namespace App\Domain\Platform\Filament\Resources\Businesses\Pages;

use App\Domain\Platform\Filament\Resources\Businesses\BusinessResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No create action — platform staff never create businesses directly
 * (see BusinessResource's class docblock: businesses only come from
 * tenant signup). The generator scaffold hardcoded a CreateAction here,
 * which opened a blank modal with no form fields and crashed on insert;
 * removed rather than gated, since this resource should never offer
 * manual creation.
 */
class ListBusinesses extends ListRecords
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
