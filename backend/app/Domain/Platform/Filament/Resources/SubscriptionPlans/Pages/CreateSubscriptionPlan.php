<?php

namespace App\Domain\Platform\Filament\Resources\SubscriptionPlans\Pages;

use App\Domain\Platform\Filament\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscriptionPlan extends CreateRecord
{
    protected static string $resource = SubscriptionPlanResource::class;
}
