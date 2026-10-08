<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\System;

use App\Enums\ScheduleTaskStatus;
use App\Models\System\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 定时任务管理模型工厂
 *
 * @extends Factory<Schedule>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $commands = ['stats:user', 'auth:clear-resets', 'model:prune', 'segments:sync-rules', 'horizon:snapshot'];

        return [
            'name' => fake()->unique()->words(2, true).' 任务',
            'command' => fake()->randomElement($commands),
            'command_custom' => null,
            'params' => [],
            'options' => [],
            'options_with_value' => [],
            'expression' => fake()->randomElement(['0 1 * * *', '*/5 * * * *', '0 2 * * 0', '0 0 * * *']),
            'environments' => null,
            'description' => fake()->sentence(),
            'even_in_maintenance_mode' => false,
            'without_overlapping' => false,
            'on_one_server' => true,
            'run_in_background' => false,
            'status' => ScheduleTaskStatus::ACTIVE,
        ];
    }

    /**
     * 自定义 Shell 命令
     */
    public function custom(string $command): static
    {
        return $this->state(fn (array $attributes): array => [
            'command' => 'custom',
            'command_custom' => $command,
        ]);
    }

    /**
     * 禁用状态
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScheduleTaskStatus::INACTIVE,
        ]);
    }

    /**
     * 带参数和选项
     */
    public function withParams(array $params, array $options = [], array $optionsWithValue = []): static
    {
        return $this->state(fn (array $attributes): array => [
            'params' => $params,
            'options' => $options,
            'options_with_value' => $optionsWithValue,
        ]);
    }
}
