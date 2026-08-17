<?php

namespace App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\Pages;

use App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\KnowledgeBaseArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKnowledgeBaseArticles extends ListRecords
{
    protected static string $resource = KnowledgeBaseArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
