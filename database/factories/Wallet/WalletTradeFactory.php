<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Database\Factories\Wallet;

use App\Enums\WalletTradeStatus;
use App\Enums\WalletTradeType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet\WalletTrade>
 */
class WalletTradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 1000);

        return [
            'owner_type' => 'user',
            'owner_id' => User::factory(),
            'amount' => $amount,
            'before_balance' => 0,
            'after_balance' => $amount,
            'before_frozen' => 0,
            'after_frozen' => 0,
            'type' => WalletTradeType::RECHARGE,
            'source_type' => null,
            'source_id' => null,
            'status' => WalletTradeStatus::SUCCESS,
            'description' => '充值',
        ];
    }

    /**
     * 充值流水
     */
    public function recharge(): static
    {
        return $this->state(function (array $attributes) {
            $amount = abs($attributes['amount'] ?? fake()->randomFloat(2, 10, 1000));

            return [
                'amount' => $amount,
                'after_balance' => $amount,
                'type' => WalletTradeType::RECHARGE,
                'description' => '充值',
            ];
        });
    }

    /**
     * 消费流水
     */
    public function payment(): static
    {
        return $this->state(function (array $attributes) {
            $amount = -abs($attributes['amount'] ?? fake()->randomFloat(2, 1, 50));

            return [
                'amount' => $amount,
                'after_balance' => $amount,
                'type' => WalletTradeType::PAYMENT,
                'description' => '消费',
            ];
        });
    }

    /**
     * 为指定用户创建流水
     */
    public function forUser(int $userId, float $balance, float $frozenBalance = 0): static
    {
        return $this->state(fn () => [
            'owner_type' => 'user',
            'owner_id' => $userId,
            'before_balance' => $balance,
            'after_balance' => $balance,
            'before_frozen' => $frozenBalance,
            'after_frozen' => $frozenBalance,
        ]);
    }
}
