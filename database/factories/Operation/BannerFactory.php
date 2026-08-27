<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Operation;

use App\Enums\LinkType;
use App\Models\Operation\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Banner 模型工厂
 *
 * @extends Factory<Banner>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class BannerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Banner>
     */
    protected $model = Banner::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $positions = ['home_top', 'home_banner', 'launch', 'user_center', 'discover'];

        return [
            'position' => fake()->randomElement($positions),
            'title' => fake()->sentence(4),
            'image' => 'https://via.placeholder.com/1200x400?text='.urlencode(fake()->sentence(2)),
            'link_type' => fake()->randomElement(LinkType::cases()),
            'link_value' => fake()->url(),
            'sort' => fake()->numberBetween(0, 100),
            'status' => true,
            'start_at' => null,
            'end_at' => null,
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
     * 仅展示无链接
     */
    public function noLink(): static
    {
        return $this->state(fn () => [
            'link_type' => LinkType::NONE,
            'link_value' => null,
        ]);
    }

    /**
     * 设置时间范围
     */
    public function withDuration(?string $startAt = null, ?string $endAt = null): static
    {
        return $this->state(fn () => [
            'start_at' => $startAt ?? now()->subDays(7),
            'end_at' => $endAt ?? now()->addDays(30),
        ]);
    }
}
