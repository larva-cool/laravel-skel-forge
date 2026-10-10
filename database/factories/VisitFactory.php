<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories;

use App\Models\Content\Visit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * 访问记录模型工厂
 *
 * @extends Factory<Visit>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class VisitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Visit>
     */
    protected $model = Visit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_type' => null,
            'source_id' => null,
            'ip' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }

    /**
     * 指定访问目标
     */
    public function forSource(Model $source): static
    {
        return $this->state(fn () => [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
        ]);
    }
}
