<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\System;

use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Models\System\SensitiveWord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 敏感词模型工厂
 *
 * @extends Factory<SensitiveWord>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SensitiveWordFactory extends Factory
{
    protected $model = SensitiveWord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'word' => fake()->unique()->word(),
            'category' => fake()->randomElement(SensitiveCategory::cases()),
            'level' => fake()->randomElement(SensitiveLevel::cases()),
            'replacement' => '*',
            'hit_count' => 0,
            'status' => true,
        ];
    }

    /**
     * 禁用状态
     */
    public function disabled(): static
    {
        return $this->state(fn () => ['status' => false]);
    }

    /**
     * 拦截级别
     */
    public function block(): static
    {
        return $this->state(fn () => ['level' => SensitiveLevel::BLOCK, 'category' => SensitiveCategory::POLITICAL]);
    }

    /**
     * 审核级别
     */
    public function review(): static
    {
        return $this->state(fn () => ['level' => SensitiveLevel::REVIEW, 'category' => SensitiveCategory::INSULT]);
    }
}
