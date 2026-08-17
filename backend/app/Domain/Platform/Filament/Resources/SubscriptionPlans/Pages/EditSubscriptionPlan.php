<?php

namespace App\Domain\Platform\Filament\Resources\SubscriptionPlans\Pages;

use App\Domain\Platform\Filament\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscriptionPlan extends EditRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
