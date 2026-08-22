<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\App;

use App\Enums\AppPlatform;
use App\Enums\ReleaseStatus;
use App\Models\App\AppVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * App 版本模型工厂
 *
 * @extends Factory<AppVersion>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $major = fake()->numberBetween(1, 5);
        $minor = fake()->numberBetween(0, 20);
        $patch = fake()->numberBetween(0, 50);
        $version = "{$major}.{$minor}.{$patch}";
        $build = $major * 10000 + $minor * 100 + $patch;

        return [
            'platform' => fake()->randomElement(AppPlatform::cases()),
            'version' => $version,
            'build' => $build,
            'download_url' => fake()->url(),
            'release_notes' => fake()->optional()->paragraph(),
            'min_supported_version' => null,
            'min_supported_build' => 0,
            'force_update' => false,
            'file_size' => fake()->optional()->numberBetween(1048576, 104857600),
            'file_hash' => fake()->optional()->sha256(),
            'status' => ReleaseStatus::DRAFT,
            'published_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * 指定平台
     */
    public function platform(AppPlatform $platform): static
    {
        return $this->state(fn (array $attributes) => ['platform' => $platform]);
    }

    /**
     * 已发布状态
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReleaseStatus::PUBLISHED,
            'published_at' => now(),
        ]);
    }

    /**
     * 强制更新
     */
    public function forceUpdate(): static
    {
        return $this->state(fn (array $attributes) => ['force_update' => true]);
    }
}
