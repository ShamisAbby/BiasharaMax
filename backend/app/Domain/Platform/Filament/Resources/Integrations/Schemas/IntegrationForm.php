<?php

namespace App\Domain\Platform\Filament\Resources\Integrations\Schemas;

use App\Domain\Integrations\Drivers\GeminiTestDriver;
use App\Domain\Integrations\Models\Integration;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class IntegrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Integration')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(table: Integration::class, ignoreRecord: true),
                    Select::make('category')
                        ->options([
                            Integration::CATEGORY_OAUTH => 'OAuth',
                            Integration::CATEGORY_MAPS => 'Maps',
                            Integration::CATEGORY_ANALYTICS => 'Analytics',
                            Integration::CATEGORY_SOCIAL_LOGIN => 'Social login',
                            Integration::CATEGORY_AI => 'AI',
                            Integration::CATEGORY_COMMUNICATION => 'Communication',
                            Integration::CATEGORY_AUTOMATION => 'Automation',
                            Integration::CATEGORY_STORAGE => 'Storage',
                            Integration::CATEGORY_CUSTOM => 'Custom',
                        ])
                        ->required(),
                    TextInput::make('provider')
                        ->required()
                        ->maxLength(40)
                        // Normalised on save so the stored value always
                        // matches a driver key. Typing "Gemini" here used
                        // to store a value that matched nothing and
                        // silently fell back to the generic HTTP driver.
                        ->dehydrateStateUsing(fn (?string $state): string => Integration::normalizeKey($state))
                        ->helperText('e.g. openai, claude, gemini, google_maps, slack — matches a built-in test driver if recognized, otherwise falls back to a generic HTTP connection test. Saved in lowercase with underscores.'),
                    Select::make('mode')
                        ->options([
                            Integration::MODE_SANDBOX => 'Sandbox',
                            Integration::MODE_PRODUCTION => 'Production',
                        ])
                        ->default(Integration::MODE_SANDBOX)
                        ->required(),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                    TextInput::make('webhook_url')
                        ->url()
                        ->maxLength(255)
                        ->columnSpan(2),
                    TextInput::make('documentation_url')
                        ->url()
                        ->maxLength(255)
                        ->columnSpan(2),
                ]),
            Section::make('Credentials')
                ->description('Stored encrypted. Leave a key blank to keep its existing stored value unchanged. Key names are matched loosely — "API Key", "api-key" and "api_key" are all read as api_key.')
                ->schema([
                    KeyValue::make('credentials')
                        ->hiddenLabel()
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        // Normalised before matching, for the same reason
                        // the driver resolver is: the provider is free
                        // text, so a value of "Gemini" would miss a
                        // lowercase `gemini` case and fall through to the
                        // generic hint — telling the reader nothing about
                        // the keys the driver actually wants.
                        ->helperText(fn (Get $get): string => match (Integration::normalizeKey($get('provider'))) {
                            'gemini' => 'Required key: api_key. Optional: model (defaults to '
                                .GeminiTestDriver::DEFAULT_MODEL
                                .'). Google retires model names periodically — set `model` to override without a deploy.',
                            'openai' => 'Required key: api_key. Optional: model.',
                            'claude' => 'Required key: api_key. Optional: model.',
                            'google_maps' => 'Required key: api_key.',
                            'slack' => 'Required key: webhook_url.',
                            default => 'Check the provider docs for the key names it expects.',
                        }),
                ]),
        ]);
    }
}
