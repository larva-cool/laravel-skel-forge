<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Wallet;

use App\Enums\WithdrawAccountStatus;
use App\Enums\WithdrawAccountType;
use App\Models\User;
use App\Models\Wallet\WithdrawAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 提现收款账户模型工厂
 *
 * @extends Factory<WithdrawAccount>
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WithdrawAccountFactory extends Factory
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
            'account_type' => WithdrawAccountType::WECHAT,
            'real_name' => fake()->name(),
            'account_no' => fake()->numerify('####################'),
            'bank_name' => null,
            'bank_branch' => null,
            'is_default' => false,
            'is_verified' => true,
            'last_used_at' => null,
            'status' => WithdrawAccountStatus::ACTIVE,
        ];
    }

    /**
     * 支付宝账户
     */
    public function alipay(): static
    {
        return $this->state(fn (array $attributes): array => [
            'account_type' => WithdrawAccountType::ALIPAY,
            'account_no' => fake()->numerify('135########'),
        ]);
    }

    /**
     * 银行卡账户
     */
    public function bank(): static
    {
        return $this->state(fn (array $attributes): array => [
            'account_type' => WithdrawAccountType::BANK,
            'account_no' => fake()->numerify('################'),
            'bank_name' => fake()->randomElement(['中国工商银行', '中国建设银行', '中国农业银行', '中国银行', '招商银行']),
            'bank_branch' => fake()->city().'支行',
        ]);
    }

    /**
     * 设为默认
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }

    /**
     * 已禁用
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WithdrawAccountStatus::DISABLED,
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
