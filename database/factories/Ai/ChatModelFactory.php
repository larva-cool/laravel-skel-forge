<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Ai;

use App\Models\Ai\ChatModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 聊天 AI 模型工厂
 *
 * @extends Factory<ChatModel>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ChatModelFactory extends Factory
{
    protected $model = ChatModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'volc',
            'model_id' => fake()->unique()->slug(3),
            'name' => fake()->words(2, true),
            'is_default' => false,
            'is_enabled' => true,
            'sort' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * 默认模型
     */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /**
     * 禁用状态
     */
    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
