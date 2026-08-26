<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Content;

use App\Enums\ReviewStatus;
use App\Models\Content\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * 评论模型工厂
 *
 * @extends Factory<Comment>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Comment>
     */
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'source_type' => null,
            'source_id' => null,
            'is_top' => false,
            'like_count' => 0,
            'comment_count' => 0,
            'status' => ReviewStatus::APPROVED,
            'content' => fake()->sentence(),
            'mentioned_users' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * 指定评论目标
     */
    public function forSource(Model $source): static
    {
        return $this->state(fn () => [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
        ]);
    }

    /**
     * 标记为置顶评论
     */
    public function top(): static
    {
        return $this->state(fn () => ['is_top' => true]);
    }

    /**
     * 标记为待审核状态
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => ReviewStatus::PENDING]);
    }

    /**
     * 附带艾特用户
     *
     * @param  array<int, int>  $userIds  被艾特的用户ID
     */
    public function withMentionedUsers(array $userIds): static
    {
        return $this->state(fn () => ['mentioned_users' => $userIds]);
    }
}
