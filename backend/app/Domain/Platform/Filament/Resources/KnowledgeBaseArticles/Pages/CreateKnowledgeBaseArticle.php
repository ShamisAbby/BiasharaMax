<?php

namespace App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\Pages;

use App\Domain\Platform\Filament\Resources\KnowledgeBaseArticles\KnowledgeBaseArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKnowledgeBaseArticle extends CreateRecord
{
    protected static string $resource = KnowledgeBaseArticleResource::class;
}
