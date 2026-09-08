<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Operation;

use App\Enums\AnnouncementStatus;
use App\Models\Operation\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 公告模型工厂
 *
 * @extends Factory<Announcement>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AnnouncementFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Announcement>
     */
    protected $model = Announcement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'summary' => fake()->sentence(15),
            'content' => '<p>'.fake()->paragraph(3).'</p>',
            'cover_image' => null,
            'status' => AnnouncementStatus::DRAFT,
            'sort' => fake()->numberBetween(0, 100),
            'admin_id' => 1,
            'published_at' => null,
        ];
    }

    /**
     * 草稿状态
     */
    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => AnnouncementStatus::DRAFT,
            'published_at' => null,
        ]);
    }

    /**
     * 已发布状态
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => AnnouncementStatus::PUBLISHED,
            'published_at' => now(),
        ]);
    }

    /**
     * 已归档状态
     */
    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => AnnouncementStatus::ARCHIVED,
            'published_at' => now()->subDays(30),
        ]);
    }
}
