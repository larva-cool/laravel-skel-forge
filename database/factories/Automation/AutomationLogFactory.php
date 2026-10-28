<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Automation;

use App\Enums\AutomationChannel;
use App\Enums\AutomationLogStatus;
use App\Models\Automation\AutomationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 自动化运营触发日志工厂
 *
 * @extends Factory<AutomationLog>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AutomationLog>
     */
    protected $model = AutomationLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_id' => 1,
            'user_id' => 1,
            'trigger_date' => now()->toDateString(),
            'festival_id' => null,
            'channels' => [AutomationChannel::DATABASE->value],
            'status' => AutomationLogStatus::SUCCESS,
            'error' => null,
        ];
    }

    /**
     * 失败状态
     */
    public function failed(string $error = '发送失败'): static
    {
        return $this->state(fn () => [
            'status' => AutomationLogStatus::FAILED,
            'error' => $error,
        ]);
    }
}
