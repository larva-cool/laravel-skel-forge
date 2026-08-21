<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\System;

use App\Enums\ScheduleStatus;
use App\Models\System\ScheduleLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 调度任务执行日志模型工厂
 *
 * @extends Factory<ScheduleLog>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['auth:clear-resets', 'model:prune', 'stats:user', 'horizon:snapshot']),
            'type' => 'command',
            'expression' => '0 1 * * *',
            'status' => ScheduleStatus::SUCCESS,
            'exit_code' => 0,
            'runtime' => fake()->randomFloat(3, 0.001, 10),
            'hostname' => fake()->domainWord(),
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }

    /**
     * 执行中状态
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleStatus::RUNNING,
            'exit_code' => null,
            'runtime' => null,
            'finished_at' => null,
        ]);
    }

    /**
     * 执行失败状态
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleStatus::FAILED,
            'exit_code' => 1,
            'runtime' => null,
            'exception' => 'RuntimeException: '.fake()->sentence(),
        ]);
    }

    /**
     * 跳过状态
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleStatus::SKIPPED,
            'exit_code' => null,
            'runtime' => null,
        ]);
    }
}
