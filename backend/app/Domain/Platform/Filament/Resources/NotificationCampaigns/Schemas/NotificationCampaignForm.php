<?php

namespace App\Domain\Platform\Filament\Resources\NotificationCampaigns\Schemas;

use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Notifications\Models\NotificationTemplate;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Field set mirrors App\Domain\Platform\Http\Requests\NotificationCampaignRequest. */
class NotificationCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('channel')->required()->maxLength(20),
                    Select::make('notification_template_id')
                        ->label('Template')
                        ->options(fn () => NotificationTemplate::query()->orderBy('name')->pluck('name', 'id')),
                    Select::make('audience_type')
                        ->options([
                            NotificationCampaign::AUDIENCE_ALL_BUSINESSES => 'All businesses',
                            NotificationCampaign::AUDIENCE_BUSINESS_TYPE => 'By business type',
                            NotificationCampaign::AUDIENCE_SUBSCRIPTION_PLAN => 'By subscription plan',
                            NotificationCampaign::AUDIENCE_SPECIFIC_BUSINESSES => 'Specific businesses',
                        ])
                        ->required()
                        ->live(),
                    DateTimePicker::make('scheduled_at')
                        ->native(false)
                        ->helperText('Leave blank to send on demand.'),
                    TextInput::make('subject')->maxLength(255)->columnSpanFull(),
                ]),
            Section::make('Audience filter')
                ->visible(fn (callable $get): bool => $get('audience_type') !== NotificationCampaign::AUDIENCE_ALL_BUSINESSES)
                ->schema([
                    KeyValue::make('audience_filter')
                        ->helperText('e.g. business_type_id / subscription_plan_id / business_ids depending on audience type.'),
                ]),
            Section::make('Content')
                ->schema([
                    Textarea::make('body')->required()->rows(8),
                ]),
        ]);
    }
}
