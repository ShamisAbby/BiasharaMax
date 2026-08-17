<?php

namespace App\Domain\Platform\Filament\Resources\NotificationTemplates\Schemas;

use App\Domain\Notifications\Models\NotificationTemplate;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotificationTemplateForm
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
                        ->unique(table: NotificationTemplate::class, ignoreRecord: true),
                    TextInput::make('channel')->required()->maxLength(20),
                    TextInput::make('category')->required()->maxLength(40),
                    TextInput::make('subject')->maxLength(255)->columnSpanFull(),
                    Toggle::make('is_active')->default(true),
                ]),
            Section::make('Content')
                ->schema([
                    Textarea::make('body')
                        ->required()
                        ->rows(10)
                        ->helperText('Supports template placeholders, e.g. {{ name }}.'),
                ]),
        ]);
    }
}
