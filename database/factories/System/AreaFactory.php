<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\System;

use App\Models\System\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 地区模型工厂
 *
 * @extends Factory<Area>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->city(),
            'area_code' => fake()->unique()->numberBetween(100000, 999999),
            'lat' => fake()->latitude(20, 50),
            'lng' => fake()->longitude(100, 140),
            'city_code' => fake()->bothify('0???'),
            'sort' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * 顶级省份
     */
    public function province(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
            'name' => fake()->unique()->state(),
            'area_code' => fake()->unique()->numberBetween(100000, 999900),
            'city_code' => null,
        ]);
    }

    /**
     * 城市级（子地区）
     */
    public function city(?int $parentId = null): static
    {
        return $this->state(function (array $attributes) use ($parentId) {
            $pid = $parentId ?? (Area::query()->inRandomOrder()->value('id')
                ?: Area::factory()->province()->create()->id);

            return [
                'parent_id' => $pid,
                'name' => fake()->unique()->city(),
                'area_code' => fake()->unique()->numberBetween(100000, 999999),
            ];
        });
    }

    /**
     * 区县（三级子地区）
     */
    public function district(?int $parentId = null): static
    {
        return $this->state(function (array $attributes) use ($parentId) {
            $pid = $parentId ?? (Area::query()->whereNotNull('parent_id')->inRandomOrder()->value('id')
                ?: Area::factory()->city()->create()->id);

            return [
                'parent_id' => $pid,
                'name' => fake()->unique()->streetName(),
                'area_code' => fake()->unique()->numberBetween(100000, 999999),
            ];
        });
    }
}
