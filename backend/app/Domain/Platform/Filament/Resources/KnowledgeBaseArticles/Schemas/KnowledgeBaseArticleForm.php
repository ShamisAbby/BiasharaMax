<?php

namespace App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\Schemas;

use App\Domain\Support\Models\KnowledgeBaseArticle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Categories have no dedicated index route in the old UI either
 * (KnowledgeBaseController::storeCategory only) — quick-add via the
 * Select's create-option form rather than a separate resource.
 */
class KnowledgeBaseArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('knowledge_base_category_id')
                ->label('Category')
                ->relationship('category', 'name')
                ->required()
                ->createOptionForm([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('slug')->required()->maxLength(255),
                    TextInput::make('icon')->maxLength(60),
                ]),
            Select::make('type')
                ->options([
                    KnowledgeBaseArticle::TYPE_ARTICLE => 'Article',
                    KnowledgeBaseArticle::TYPE_FAQ => 'FAQ',
                    KnowledgeBaseArticle::TYPE_GUIDE => 'Guide',
                ])
                ->required(),
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create' ? $set('slug', str($state)->slug()) : null),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(table: KnowledgeBaseArticle::class, ignoreRecord: true),
            Toggle::make('is_published')->default(true),
            Textarea::make('content')->required()->rows(10)->columnSpanFull(),
        ]);
    }
}
