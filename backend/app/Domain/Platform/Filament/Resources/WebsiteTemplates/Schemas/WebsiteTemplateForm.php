<?php

namespace App\Domain\Platform\Filament\Resources\WebsiteTemplates\Schemas;

use App\Domain\Business\Models\BusinessType;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Field set mirrors App\Domain\Platform\Http\Requests\WebsiteTemplateRequest
 * and WebsiteTemplatePageRequest. The many JSON-blob config fields
 * (theme_colors, typography, header/footer/navigation_config,
 * seo_settings, social_media) are generic KeyValue editors rather than
 * bespoke per-field sub-forms — same pragmatic approach as
 * PaymentGateway's `credentials`. Pages are a relationship Repeater,
 * equivalent to the old UI's separate storePage/updatePage/destroyPage
 * routes (Filament's relationship-backed Repeater creates/updates/
 * deletes child rows on save).
 */
class WebsiteTemplateForm
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
                        ->unique(table: WebsiteTemplate::class, ignoreRecord: true),
                    Select::make('business_type_id')
                        ->label('Business type')
                        ->options(fn () => BusinessType::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    TextInput::make('preview_url')->url()->maxLength(255),
                    TextInput::make('thumbnail_path')->maxLength(255),
                    TextInput::make('whatsapp_number')->maxLength(32),
                    TextInput::make('sort_order')->numeric()->integer()->default(0),
                    Toggle::make('is_default'),
                    Textarea::make('description')->columnSpanFull(),
                ]),

            Section::make('Appearance & config')
                ->columns(2)
                ->collapsed()
                ->schema([
                    KeyValue::make('theme_colors'),
                    KeyValue::make('typography'),
                    KeyValue::make('header_config'),
                    KeyValue::make('footer_config'),
                    KeyValue::make('navigation_config'),
                    KeyValue::make('seo_settings'),
                    KeyValue::make('social_media'),
                    Textarea::make('custom_css')->columnSpanFull(),
                    Textarea::make('google_maps_embed')->columnSpanFull(),
                    Textarea::make('analytics_code')->columnSpanFull(),
                ]),

            Section::make('Plans')
                ->schema([
                    Select::make('subscriptionPlans')
                        ->label('Available on plans')
                        ->relationship('subscriptionPlans', 'name')
                        ->multiple()
                        ->preload()
                        ->options(fn () => SubscriptionPlan::query()->orderBy('sort_order')->pluck('name', 'id')),
                ]),

            Section::make('Pages')
                ->schema([
                    Repeater::make('pages')
                        ->relationship('pages')
                        ->schema([
                            Select::make('type')
                                ->options(array_combine(WebsiteTemplate::PAGE_TYPES, array_map(fn (string $type) => str($type)->headline()->toString(), WebsiteTemplate::PAGE_TYPES)))
                                ->required(),
                            TextInput::make('title')->required()->maxLength(255),
                            TextInput::make('slug')->required()->maxLength(255),
                            TextInput::make('sort_order')->numeric()->integer()->default(0),
                            Toggle::make('is_enabled')->default(true),
                            KeyValue::make('content')->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->addActionLabel('Add page'),
                ]),
        ]);
    }
}
