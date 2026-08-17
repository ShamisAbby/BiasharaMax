<?php

namespace App\Domain\Platform\Filament\Resources\PaymentGateways\Pages;

use App\Domain\Platform\Filament\Resources\PaymentGateways\PaymentGatewayResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentGateway extends CreateRecord
{
    protected static string $resource = PaymentGatewayResource::class;
}
