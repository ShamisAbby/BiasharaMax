<?php

namespace App\Domain\Website\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Website\Models\Article;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('website.view');
    }

    public function view(User $user, Article $article): bool
    {
        return $user->business_id === $article->business_id && $user->hasPermission('website.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('website.manage');
    }

    public function manage(User $user, Article $article): bool
    {
        return $user->business_id === $article->business_id && $user->hasPermission('website.manage');
    }
}
