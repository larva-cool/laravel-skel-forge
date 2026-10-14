<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Content;

use App\Enums\ReportStatus;
use App\Models\Content\Comment;
use App\Models\Content\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * 举报模型工厂
 *
 * @extends Factory<Report>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ReportFactory extends Factory
{
    /**
     * @var class-string<Report>
     */
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reportable_id' => null,
            'reportable_type' => null,
            'category' => 'other',
            'reason' => fake()->sentence(),
            'evidence' => null,
            'status' => ReportStatus::PENDING,
            'handler_id' => null,
            'process_remark' => null,
            'process_actions' => null,
            'processed_at' => null,
        ];
    }

    /**
     * 使用闭包形式返回 user_id，以便在创建时解析为已存在的用户ID
     */
    public function withUser(int $userId): static
    {
        return $this->state(fn () => ['user_id' => $userId]);
    }

    /**
     * 指定被举报对象
     */
    public function forTarget(Model $target): static
    {
        return $this->state(fn () => [
            'reportable_id' => $target->getKey(),
            'reportable_type' => $target->getMorphClass(),
        ]);
    }

    /**
     * 标记为已处理状态
     */
    public function resolved(): static
    {
        return $this->state(fn () => ['status' => ReportStatus::RESOLVED]);
    }

    /**
     * 标记为已驳回
     */
    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ReportStatus::REJECTED]);
    }

    /**
     * 举报一个评论（默认以未指定 user 的方式被 forTarget 调用）
     */
    public function forComment(Comment $comment): static
    {
        return $this->forTarget($comment);
    }
}
