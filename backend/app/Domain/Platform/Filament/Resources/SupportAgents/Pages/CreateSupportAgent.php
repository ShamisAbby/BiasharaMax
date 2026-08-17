<?php

namespace App\Domain\Platform\Filament\Resources\SupportAgents\Pages;

use App\Domain\Platform\Filament\Resources\SupportAgents\SupportAgentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportAgent extends CreateRecord
{
    protected static string $resource = SupportAgentResource::class;
}
