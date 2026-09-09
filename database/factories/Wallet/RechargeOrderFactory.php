<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Wallet;

use App\Enums\PayChannel;
use App\Enums\RechargeStatus;
use App\Models\User;
use App\Models\Wallet\RechargeOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * 充值订单模型工厂
 *
 * @extends Factory<RechargeOrder>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RechargeOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => 'user',
            'owner_id' => User::factory(),
            'order_no' => RechargeOrder::generateOrderNo(),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'bonus_amount' => 0,
            'pay_channel' => PayChannel::MANUAL,
            'pay_status' => RechargeStatus::PENDING,
            'expired_at' => Carbon::now()->addMinutes(30),
            'client_ip' => fake()->ipv4(),
        ];
    }

    /**
     * 已到账订单
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pay_status' => RechargeStatus::SUCCESS,
            'paid_at' => Carbon::now(),
        ]);
    }

    /**
     * 已过期订单
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pay_status' => RechargeStatus::EXPIRED,
            'expired_at' => Carbon::now()->subMinutes(10),
        ]);
    }

    /**
     * 待支付但已超时（尚未被关单任务处理）
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pay_status' => RechargeStatus::PENDING,
            'expired_at' => Carbon::now()->subMinutes(10),
        ]);
    }

    /**
     * 带赠送金额
     */
    public function withBonus(float $bonus): static
    {
        return $this->state(fn (array $attributes): array => [
            'bonus_amount' => $bonus,
        ]);
    }

    /**
     * 指定持有者
     */
    public function forUser(User|int $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'owner_type' => 'user',
            'owner_id' => $user instanceof User ? $user->id : $user,
        ]);
    }
}
