<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Wallet;

use App\Enums\WithdrawAccountType;
use App\Enums\WithdrawStatus;
use App\Models\User;
use App\Models\Wallet\Withdraw;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * 提现申请模型工厂
 *
 * @extends Factory<Withdraw>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 1000);
        $fee = round($amount * 0.001, 2);

        return [
            'owner_type' => 'user',
            'owner_id' => User::factory(),
            'order_no' => Withdraw::generateOrderNo(),
            'amount' => $amount,
            'fee' => $fee,
            'actual_amount' => round($amount - $fee, 2),
            'account_type' => WithdrawAccountType::WECHAT,
            'account_info' => ['account' => fake()->word(), 'real_name' => '张三'],
            'status' => WithdrawStatus::PENDING,
            'client_ip' => fake()->ipv4(),
        ];
    }

    /**
     * 已审核通过（待打款）
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WithdrawStatus::APPROVED,
            'reviewed_by' => 1,
            'reviewed_at' => Carbon::now(),
        ]);
    }

    /**
     * 已驳回
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WithdrawStatus::REJECTED,
            'reviewed_by' => 1,
            'reviewed_at' => Carbon::now(),
            'reject_reason' => '收款账户信息有误',
        ]);
    }

    /**
     * 打款成功
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WithdrawStatus::SUCCESS,
            'reviewed_by' => 1,
            'reviewed_at' => Carbon::now()->subMinutes(10),
            'transfer_at' => Carbon::now(),
        ]);
    }

    /**
     * 打款失败
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WithdrawStatus::FAILED,
            'reviewed_by' => 1,
            'reviewed_at' => Carbon::now()->subMinutes(10),
            'transfer_at' => Carbon::now(),
            'fail_reason' => '银行账户异常',
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

    /**
     * 指定金额并将手续费归零（简化余额断言）
     */
    public function noFee(): static
    {
        return $this->state(fn (array $attributes): array => [
            'fee' => 0,
            'actual_amount' => $attributes['amount'],
        ]);
    }
}
