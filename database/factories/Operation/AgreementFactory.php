<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Operation;

use App\Enums\AgreementStatus;
use App\Models\Operation\Agreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 协议模型工厂
 *
 * @extends Factory<Agreement>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AgreementFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Agreement>
     */
    protected $model = Agreement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'category' => 'user_agreement',
            'content' => '<p>'.fake()->paragraph(3).'</p>',
            'version' => 'v'.fake()->numberBetween(1, 5).'.'.fake()->numberBetween(0, 9),
            'status' => AgreementStatus::DRAFT,
            'effective_at' => null,
            'sort' => fake()->numberBetween(0, 100),
            'admin_id' => 1,
        ];
    }

    /**
     * 草稿状态
     */
    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => AgreementStatus::DRAFT,
            'effective_at' => null,
        ]);
    }

    /**
     * 已发布状态
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => AgreementStatus::PUBLISHED,
            'effective_at' => now(),
        ]);
    }

    /**
     * 已归档状态
     */
    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => AgreementStatus::ARCHIVED,
            'effective_at' => now()->subDays(30),
        ]);
    }
}
