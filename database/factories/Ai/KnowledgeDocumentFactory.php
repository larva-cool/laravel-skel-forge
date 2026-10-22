<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Ai;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\Ai\KnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 知识库文档工厂
 *
 * @extends Factory<KnowledgeDocument>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class KnowledgeDocumentFactory extends Factory
{
    protected $model = KnowledgeDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'content' => fake()->paragraphs(3, true),
            'status' => KnowledgeDocumentStatus::PENDING,
            'is_enabled' => true,
            'chunks_count' => 0,
            'tokens' => 0,
            'error' => null,
            'created_by' => null,
        ];
    }

    /**
     * 已完成索引
     */
    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => KnowledgeDocumentStatus::READY,
            'chunks_count' => fake()->numberBetween(1, 10),
        ]);
    }

    /**
     * 索引失败
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => KnowledgeDocumentStatus::FAILED,
            'error' => '模拟索引失败',
        ]);
    }

    /**
     * 停用状态
     */
    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
