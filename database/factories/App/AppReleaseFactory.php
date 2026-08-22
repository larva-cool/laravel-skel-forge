<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\App;

use App\Models\App\AppRelease;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 应用发布模型工厂
 *
 * @extends Factory<AppRelease>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'icon' => fake()->optional()->imageUrl(64, 64),
            'sort' => fake()->numberBetween(0, 100),
            'status' => true,
        ];
    }
}
