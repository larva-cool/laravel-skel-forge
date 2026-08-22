<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FeedbackPriority;
use App\Enums\FeedbackStatus;
use App\Models\System\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 用户反馈模型工厂
 *
 * @extends Factory<Feedback>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class FeedbackFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Feedback>
     */
    protected $model = Feedback::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['feature', 'experience', 'bug', 'other'];

        return [
            'title' => fake()->sentence(6),
            'content' => fake()->paragraph(3),
            'images' => [],
            'type' => fake()->randomElement($types),
            'status' => FeedbackStatus::PENDING,
            'priority' => fake()->randomElement(FeedbackPriority::cases()),
            'contact_name' => fake()->name(),
            'contact_info' => fake()->safeEmail(),
            'user_agent' => fake()->userAgent(),
            'ip_address' => fake()->ipv4(),
            'user_id' => null,
            'admin_id' => null,
            'admin_remark' => null,
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    /**
     * 标记为待处理状态
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => FeedbackStatus::PENDING]);
    }

    /**
     * 标记为处理中状态
     */
    public function processing(): static
    {
        return $this->state(fn () => ['status' => FeedbackStatus::PROCESSING]);
    }

    /**
     * 标记为已解决状态
     */
    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => FeedbackStatus::RESOLVED,
            'resolved_at' => now(),
        ]);
    }

    /**
     * 标记为已关闭状态
     */
    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => FeedbackStatus::CLOSED,
            'closed_at' => now(),
        ]);
    }

    /**
     * 附带示例图片
     *
     * @param  int  $count  图片数量（1-9）
     */
    public function withImages(int $count = 2): static
    {
        return $this->state(fn () => [
            'images' => collect(range(1, min(max(1, $count), 9)))
                ->map(fn () => 'https://example.com/images/feedback/'.fake()->uuid().'.jpg')
                ->toArray(),
        ]);
    }
}
