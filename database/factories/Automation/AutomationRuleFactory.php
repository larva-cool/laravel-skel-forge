<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Automation;

use App\Enums\AutomationChannel;
use App\Enums\AutomationRewardType;
use App\Enums\AutomationTriggerType;
use App\Models\Automation\AutomationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 自动化运营规则工厂
 *
 * @extends Factory<AutomationRule>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AutomationRuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AutomationRule>
     */
    protected $model = AutomationRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '规则-'.fake()->unique()->word(),
            'trigger_type' => AutomationTriggerType::BIRTHDAY,
            'trigger_config' => ['advance_days' => 0, 'send_time' => '09:00'],
            'segment_id' => null,
            'title' => '生日快乐，{nickname}！',
            'content' => '亲爱的 {nickname}，祝你生日快乐！',
            'channels' => [AutomationChannel::DATABASE->value],
            'reward_type' => AutomationRewardType::NONE,
            'reward_amount' => 0,
            'status' => true,
            'admin_id' => null,
        ];
    }

    /**
     * 节日提醒类型
     */
    public function festival(): static
    {
        return $this->state(fn () => [
            'trigger_type' => AutomationTriggerType::FESTIVAL,
            'trigger_config' => ['advance_days' => 0, 'send_time' => '09:00', 'festival_ids' => []],
        ]);
    }

    /**
     * 注册周年类型
     */
    public function registerAnniversary(): static
    {
        return $this->state(fn () => ['trigger_type' => AutomationTriggerType::REGISTER_ANNIVERSARY]);
    }

    /**
     * 附带积分奖励
     */
    public function withPointReward(int $amount = 100): static
    {
        return $this->state(fn () => [
            'reward_type' => AutomationRewardType::POINT,
            'reward_amount' => $amount,
        ]);
    }

    /**
     * 附带金币奖励
     */
    public function withCoinReward(int $amount = 10): static
    {
        return $this->state(fn () => [
            'reward_type' => AutomationRewardType::COIN,
            'reward_amount' => $amount,
        ]);
    }

    /**
     * 禁用状态
     */
    public function disabled(): static
    {
        return $this->state(fn () => ['status' => false]);
    }
}
