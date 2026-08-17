<?php

namespace App\Domain\Platform\Filament\Resources\SupportAgents\Pages;

use App\Domain\Platform\Filament\Resources\SupportAgents\SupportAgentResource;
use Filament\Resources\Pages\EditRecord;

class EditSupportAgent extends EditRecord
{
    protected static string $resource = SupportAgentResource::class;
}
