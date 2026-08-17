<?php

namespace Database\Factories;

use App\Domain\Support\Models\KnowledgeBaseArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeBaseArticle>
 */
class KnowledgeBaseArticleFactory extends Factory
{
    protected $model = KnowledgeBaseArticle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => KnowledgeBaseArticle::TYPE_ARTICLE,
            'title' => fake()->sentence(),
            'slug' => fake()->unique()->slug(3),
            'content' => fake()->paragraphs(3, true),
            'is_published' => false,
            'view_count' => 0,
        ];
    }
}
