<?php

namespace App\Domain\Platform\Filament\Resources\NotificationChannels\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Field set mirrors App\Domain\Platform\Http\Requests\NotificationChannelRequest. */
class NotificationChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('channel')->required()->maxLength(20)->helperText('e.g. sms, email, whatsapp, push.'),
                    TextInput::make('provider')->required()->maxLength(40),
                    TextInput::make('sender_id')->maxLength(255),
                    TextInput::make('webhook_url')->url()->maxLength(255),
                    TextInput::make('sort_order')->numeric()->integer()->default(0),
                ]),
            Section::make('Credentials')
                ->description('Leave a value blank to keep the existing stored credential unchanged.')
                ->schema([
                    KeyValue::make('credentials'),
                ]),
        ]);
    }
}
