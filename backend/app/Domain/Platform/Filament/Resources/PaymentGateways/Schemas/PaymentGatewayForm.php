<?php

namespace App\Domain\Platform\Filament\Resources\PaymentGateways\Schemas;

use App\Domain\Finance\Models\PaymentGateway;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Field set mirrors App\Domain\Platform\Http\Requests\PaymentGatewayRequest.
 * `credentials` is provider-defined key/value pairs (API keys, secrets)
 * — edited as a generic KeyValue field. The "blank means leave unchanged"
 * merge behavior from the old controller's update() is replicated in
 * EditPaymentGateway::mutateFormDataBeforeSave(), not here.
 */
class PaymentGatewayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create' ? $set('slug', str($state)->slug()) : null),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(table: PaymentGateway::class, ignoreRecord: true),
                    TextInput::make('provider')->required()->maxLength(40),
                    Select::make('mode')
                        ->options([
                            PaymentGateway::MODE_SANDBOX => 'Sandbox',
                            PaymentGateway::MODE_PRODUCTION => 'Production',
                        ])
                        ->required(),
                    TextInput::make('fee_percentage')->numeric()->minValue(0)->maxValue(100),
                    TextInput::make('fee_fixed')->numeric()->minValue(0),
                    TextInput::make('priority')->numeric()->integer()->minValue(0),
                    TextInput::make('sort_order')->numeric()->integer()->minValue(0),
                    TextInput::make('webhook_url')->url()->maxLength(255),
                    TextInput::make('webhook_secret')->password()->revealable(),
                    TextInput::make('documentation_url')->url()->maxLength(255)->columnSpanFull(),
                ]),

            Section::make('Coverage')
                ->columns(2)
                ->schema([
                    TagsInput::make('supported_currencies')
                        ->helperText('3-letter currency codes, e.g. TZS, USD.'),
                    TagsInput::make('supported_countries')
                        ->helperText('2-letter country codes, e.g. TZ, KE.'),
                ]),

            Section::make('Credentials')
                ->description('Leave a value blank to keep the existing stored credential unchanged.')
                ->schema([
                    KeyValue::make('credentials')
                        ->keyLabel('Key')
                        ->valueLabel('Value'),
                ]),
        ]);
    }
}
