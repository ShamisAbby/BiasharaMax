<?php

namespace App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles;

use App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\Pages\CreateKnowledgeBaseArticle;
use App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\Pages\EditKnowledgeBaseArticle;
use App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\Pages\ListKnowledgeBaseArticles;
use App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\Schemas\KnowledgeBaseArticleForm;
use App\Domain\Support\Models\KnowledgeBaseArticle;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KnowledgeBaseArticleResource extends Resource
{
    protected static ?string $model = KnowledgeBaseArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Knowledge Base';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return KnowledgeBaseArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('type')->badge(),
                IconColumn::make('is_published')->boolean(),
                TextColumn::make('view_count')->label('Views'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        KnowledgeBaseArticle::TYPE_ARTICLE => 'Article',
                        KnowledgeBaseArticle::TYPE_FAQ => 'FAQ',
                        KnowledgeBaseArticle::TYPE_GUIDE => 'Guide',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnowledgeBaseArticles::route('/'),
            'create' => CreateKnowledgeBaseArticle::route('/create'),
            'edit' => EditKnowledgeBaseArticle::route('/{record}/edit'),
        ];
    }
}
