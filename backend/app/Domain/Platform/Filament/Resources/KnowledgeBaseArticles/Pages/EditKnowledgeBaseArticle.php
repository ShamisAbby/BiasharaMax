<?php

namespace App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\Pages;

use App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\KnowledgeBaseArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKnowledgeBaseArticle extends EditRecord
{
    protected static string $resource = KnowledgeBaseArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
