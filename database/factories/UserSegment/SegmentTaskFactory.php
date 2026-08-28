<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\UserSegment;

use App\Enums\SegmentTaskStatus;
use App\Enums\SegmentTaskType;
use App\Models\UserSegment\SegmentTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * SegmentTask 模型工厂
 *
 * @extends Factory<SegmentTask>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class SegmentTaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SegmentTask>
     */
    protected $model = SegmentTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => 1,
            'type' => SegmentTaskType::ATTACH,
            'payload' => [
                'segment_ids' => [],
                'user_ids' => [],
            ],
            'total_count' => 100,
            'processed_count' => 0,
            'added_count' => 0,
            'status' => SegmentTaskStatus::PENDING,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    /**
     * 已完成
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => SegmentTaskStatus::COMPLETED,
            'processed_count' => 100,
            'added_count' => 100,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    /**
     * 失败
     */
    public function failed(string $reason = '示例失败原因'): static
    {
        return $this->state(fn () => [
            'status' => SegmentTaskStatus::FAILED,
            'error_message' => $reason,
            'finished_at' => now(),
        ]);
    }
}
